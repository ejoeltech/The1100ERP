<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Fixed datasets the AI assistant may query. The model never supplies SQL:
 * it only names a dataset and a small set of filters, all of which are
 * validated and bound here.
 */
function aiChatDatasets()
{
    return [
        'quotes' => [
            'table' => 'quotes', 'alias' => 'q',
            'columns' => ['q.id', 'q.quote_number', 'q.quote_title', 'q.customer_name', 'q.salesperson', 'q.quote_date', 'q.grand_total', 'q.status'],
            'date_column' => 'q.quote_date',
            'text_columns' => ['q.quote_number', 'q.quote_title', 'q.customer_name', 'q.salesperson'],
            'status_column' => 'q.status', 'customer_column' => 'q.customer_name',
            'where' => ['q.deleted_at IS NULL'], 'order' => 'q.quote_date DESC, q.id DESC',
            'permission' => null, 'scope' => true,
        ],
        'invoices' => [
            'table' => 'invoices', 'alias' => 'i',
            'columns' => ['i.id', 'i.invoice_number', 'i.invoice_title', 'i.customer_name', 'i.salesperson', 'i.invoice_date', 'i.grand_total', 'i.amount_paid', 'i.balance_due', 'i.status'],
            'date_column' => 'i.invoice_date',
            'text_columns' => ['i.invoice_number', 'i.invoice_title', 'i.customer_name', 'i.salesperson'],
            'status_column' => 'i.status', 'customer_column' => 'i.customer_name',
            'where' => ['i.deleted_at IS NULL'], 'order' => 'i.invoice_date DESC, i.id DESC',
            'permission' => null, 'scope' => true,
        ],
        'receipts' => [
            'table' => 'receipts', 'alias' => 'r',
            'columns' => ['r.id', 'r.receipt_number', 'r.invoice_id', 'r.customer_name', 'r.amount_paid', 'r.payment_method', 'r.payment_date', 'r.status'],
            'date_column' => 'r.payment_date',
            'text_columns' => ['r.receipt_number', 'r.customer_name', 'r.payment_method'],
            'status_column' => 'r.status', 'customer_column' => 'r.customer_name',
            'where' => ['r.deleted_at IS NULL'], 'order' => 'r.payment_date DESC, r.id DESC',
            'permission' => null, 'scope' => true,
        ],
        'payments' => [
            'table' => 'payments', 'alias' => 'p',
            'columns' => ['p.id', 'p.payment_number', 'p.customer_id', 'p.amount', 'p.payment_date', 'p.payment_method', 'p.reference'],
            'date_column' => 'p.payment_date',
            'text_columns' => ['p.payment_number', 'p.payment_method', 'p.reference'],
            'status_column' => null, 'customer_column' => null,
            'where' => ['p.deleted_at IS NULL'], 'order' => 'p.payment_date DESC, p.id DESC',
            'permission' => ['view_payments'], 'scope' => true,
        ],
        'customers' => [
            'table' => 'customers', 'alias' => 'c',
            'columns' => ['c.id', 'c.customer_name', 'c.email', 'c.phone', 'c.city', 'c.state', 'c.account_balance'],
            'date_column' => 'c.created_at',
            'text_columns' => ['c.customer_name', 'c.email', 'c.phone', 'c.city', 'c.state'],
            'status_column' => null, 'customer_column' => 'c.customer_name',
            'where' => ['c.deleted_at IS NULL'], 'order' => 'c.customer_name ASC',
            'permission' => ['manage_customers', 'view_all_documents'], 'scope' => false,
        ],
        'products' => [
            'table' => 'products', 'alias' => 'pr',
            'columns' => ['pr.id', 'pr.product_code', 'pr.product_name', 'pr.category', 'pr.unit_price', 'pr.is_active'],
            'date_column' => 'pr.created_at',
            'text_columns' => ['pr.product_code', 'pr.product_name', 'pr.category'],
            'status_column' => null, 'customer_column' => null,
            'where' => ['pr.deleted_at IS NULL'], 'order' => 'pr.product_name ASC',
            'permission' => [], 'scope' => false,
        ],
        'leads' => [
            'table' => 'leads', 'alias' => 'l',
            'columns' => ['l.id', 'l.name', 'l.phone', 'l.email', 'l.source', 'l.status', 'l.created_at'],
            'date_column' => 'l.created_at',
            'text_columns' => ['l.name', 'l.phone', 'l.email'],
            'status_column' => 'l.status', 'customer_column' => null,
            'where' => [], 'order' => 'l.created_at DESC, l.id DESC',
            'permission' => ['manage_leads'], 'scope' => false,
        ],
        'employees' => [
            'table' => null, 'alias' => 'e',
            'sql' => "SELECT e.id, e.employee_code, u.full_name, u.email, u.phone,
                             d.name AS department, des.title AS designation,
                             e.employment_status, e.join_date
                      FROM hr_employees e
                      JOIN users u ON u.id = e.user_id
                      LEFT JOIN hr_departments d ON d.id = e.department_id
                      LEFT JOIN hr_designations des ON des.id = e.designation_id",
            'date_column' => 'e.join_date',
            'text_columns' => ['u.full_name', 'u.email', 'u.phone', 'e.employee_code', 'd.name', 'des.title'],
            'status_column' => 'e.employment_status', 'customer_column' => null,
            'where' => [], 'order' => 'u.full_name ASC',
            'permission' => ['hr_view', 'hr_manage'], 'scope' => false,
        ],
    ];
}

/**
 * Run a validated AI chat data request. Returns ['data' => rows, 'count' => n].
 * Throws a user-safe Exception when the request is not allowed.
 */
function aiChatRunQuery($pdo, $request)
{
    $datasets = aiChatDatasets();
    $key = strtolower(trim((string)($request['dataset'] ?? '')));
    if (!isset($datasets[$key])) {
        throw new Exception('That data request is not supported. Try asking about quotes, invoices, receipts, payments, customers, products, leads, or employees.');
    }
    $def = $datasets[$key];

    // Permission gate. Empty array = any logged-in user.
    if (!empty($def['permission'])) {
        $allowed = false;
        foreach ($def['permission'] as $perm) {
            if (function_exists('hasPermission') && hasPermission($perm)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new Exception('You do not have permission to query that data.');
        }
    }

    $filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];
    $where = $def['where'] ?? [];
    $params = [];

    // Role-based document scope (sales reps see only their own rows).
    if (!empty($def['scope']) && function_exists('getRoleFilter')) {
        $role = getRoleFilter($def['alias']);
        $roleSql = trim((string)($role['sql'] ?? ''));
        if ($roleSql !== '') {
            $where[] = preg_replace('/^\s*AND\s+/i', '', $roleSql);
            $params = array_merge($params, $role['params'] ?? []);
        }
    }

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '' && !empty($def['status_column'])) {
        $where[] = $def['status_column'] . ' = ?';
        $params[] = $status;
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '' && !empty($def['text_columns'])) {
        $search = substr($search, 0, 100);
        $parts = [];
        foreach ($def['text_columns'] as $col) {
            $parts[] = "$col LIKE ?";
            $params[] = '%' . $search . '%';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $customer = trim((string)($filters['customer'] ?? ''));
    if ($customer !== '' && !empty($def['customer_column'])) {
        $customer = substr($customer, 0, 100);
        $where[] = $def['customer_column'] . ' LIKE ?';
        $params[] = '%' . $customer . '%';
    }

    $from = trim((string)($filters['date_from'] ?? ''));
    $to = trim((string)($filters['date_to'] ?? ''));
    if (!empty($def['date_column'])) {
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = $def['date_column'] . ' >= ?';
            $params[] = $from;
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = $def['date_column'] . ' <= ?';
            $params[] = $to;
        }
    }

    $id = filter_var($filters['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id !== false && $id !== null && $id > 0) {
        $where[] = $def['alias'] . '.id = ?';
        $params[] = $id;
    }

    $limit = filter_var($filters['limit'] ?? 5, FILTER_VALIDATE_INT);
    $limit = $limit === false ? 5 : max(1, min(20, (int)$limit));

    if (!empty($def['sql'])) {
        $sql = $def['sql'];
    } else {
        $sql = 'SELECT ' . implode(', ', $def['columns'])
             . ' FROM `' . $def['table'] . '` ' . $def['alias'];
    }
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $def['order'] . ' LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ['data' => $rows, 'count' => count($rows)];
}
