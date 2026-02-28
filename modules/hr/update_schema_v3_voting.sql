-- HR Module Schema Update v3
-- Adds support for Staff of the Month Voting

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `hr_votes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `voter_id` int(11) unsigned NOT NULL, -- Employee who is voting
  `candidate_id` int(11) unsigned NOT NULL, -- Employee being voted for
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `reason` text, -- Optional reason for vote
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`voter_id`, `month`, `year`), -- One vote per employee per month
  FOREIGN KEY (`voter_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`candidate_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
