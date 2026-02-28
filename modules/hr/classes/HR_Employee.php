<?php
class HR_Employee
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllEmployees($limit = 50, $offset = 0)
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, u.full_name, u.email, u.phone, u.username, d.name as department, des.title as designation
            FROM hr_employees e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN hr_departments d ON e.department_id = d.id
            LEFT JOIN hr_designations des ON e.designation_id = des.id
            ORDER BY u.full_name ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getEmployeeById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, u.full_name, u.email, u.phone, u.username, u.is_active, 
                   d.name as department, des.title as designation
            FROM hr_employees e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN hr_departments d ON e.department_id = d.id
            LEFT JOIN hr_designations des ON e.designation_id = des.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getEmployeeByUserId($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, u.full_name, u.email, u.phone
            FROM hr_employees e
            JOIN users u ON e.user_id = u.id
            WHERE e.user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }

    public function createEmployee($data)
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Create User Account if user_id is not provided
            if (empty($data['user_id'])) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (username, password, full_name, email, phone, role) 
                    VALUES (?, ?, ?, ?, ?, 'viewer')
                ");
                // Default password is 'password123' - should be changed
                $hashed_password = password_hash('password123', PASSWORD_DEFAULT);
                $stmt->execute([
                    $data['email'], // Username is email by default for auto-create
                    $hashed_password,
                    $data['full_name'],
                    $data['email'],
                    $data['phone']
                ]);
                $user_id = $this->pdo->lastInsertId();
            } else {
                $user_id = $data['user_id'];
            }

            // 2. Create Employee Record
            $stmt = $this->pdo->prepare("
                INSERT INTO hr_employees (
                    user_id, employee_code, department_id, designation_id, join_date, 
                    employment_status, date_of_birth, gender, address, 
                    bank_name, account_number, account_name, 
                    basic_salary, housing_allowance, transport_allowance,
                    passport_path, signature_path, secondary_phone, nin_number, bvn_number, tin_number,
                    next_of_kin_name, next_of_kin_phone, next_of_kin_relationship,
                    reference_1_name, reference_1_phone, reference_1_org,
                    reference_2_name, reference_2_phone, reference_2_org
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $user_id,
                $data['employee_code'],
                $data['department_id'] ?? null,
                $data['designation_id'] ?? null,
                $data['join_date'],
                $data['employment_status'] ?? 'full_time',
                $data['date_of_birth'] ?? null,
                $data['gender'] ?? null,
                $data['address'] ?? null,
                $data['bank_name'] ?? null,
                $data['account_number'] ?? null,
                $data['account_name'] ?? null,
                $data['basic_salary'] ?? 0,
                $data['housing_allowance'] ?? 0,
                $data['transport_allowance'] ?? 0,
                $data['passport_path'] ?? null,
                $data['signature_path'] ?? null,
                $data['secondary_phone'] ?? null,
                $data['nin_number'] ?? null,
                $data['bvn_number'] ?? null,
                $data['tin_number'] ?? null,
                $data['next_of_kin_name'] ?? null,
                $data['next_of_kin_phone'] ?? null,
                $data['next_of_kin_relationship'] ?? null,
                $data['reference_1_name'] ?? null,
                $data['reference_1_phone'] ?? null,
                $data['reference_1_org'] ?? null,
                $data['reference_2_name'] ?? null,
                $data['reference_2_phone'] ?? null,
                $data['reference_2_org'] ?? null
            ]);

            $employee_id = $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $employee_id;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateEmployee($id, $data)
    {
        // Only updates employee specific fields, not main user account (separate process usually)
        $fields = [];
        $values = [];

        $allowed = [
            'full_name',
            'department_id',
            'designation_id',
            'employment_status',
            'email',
            'address',
            'phone',
            'date_of_birth',
            'gender',
            'join_date',
            'termination_date',
            'passport_path',
            'signature_path',
            'secondary_phone',
            'nin_number',
            'bvn_number',
            'tin_number',
            'basic_salary',
            'housing_allowance',
            'transport_allowance',
            'next_of_kin_name',
            'next_of_kin_phone',
            'next_of_kin_relationship',
            'reference_1_name',
            'reference_1_phone',
            'reference_1_org',
            'reference_2_name',
            'reference_2_phone',
            'reference_2_org',
            'bank_name',
            'account_number',
            'account_name'
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (empty($fields))
            return false;

        $values[] = $id;
        $sql = "UPDATE hr_employees SET " . implode(', ', $fields) . " WHERE id = ?";
        return $this->pdo->prepare($sql)->execute($values);
    }

    public function getDepartments()
    {
        return $this->pdo->query("SELECT * FROM hr_departments ORDER BY name")->fetchAll();
    }

    public function getDesignations()
    {
        return $this->pdo->query("SELECT * FROM hr_designations ORDER BY title")->fetchAll();
    }
}
