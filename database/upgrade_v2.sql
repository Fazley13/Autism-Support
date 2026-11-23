-- =====================================================================
-- ParentConnect v2 additions (safe for a fresh import AND for upgrading
-- an existing database imported from the original dump – run it ONCE).
-- Already included at the bottom of autism_support_system.sql.
-- =====================================================================
SET NAMES utf8mb4;

-- 1) Uploaded images live in the database (Vercel has a read-only disk)
CREATE TABLE IF NOT EXISTS `media` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `mime` varchar(60) NOT NULL,
  `size` int(11) NOT NULL DEFAULT 0,
  `data` longblob NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Sessions live in the database (serverless = no shared disk)
CREATE TABLE IF NOT EXISTS `php_sessions` (
  `id` varchar(128) NOT NULL,
  `data` mediumblob NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) Private messaging between users
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pair` (`sender_id`,`receiver_id`,`id`),
  KEY `inbox` (`receiver_id`,`is_read`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4) Notifications can link to the thing they are about
ALTER TABLE `notifications` ADD COLUMN `link` varchar(255) DEFAULT NULL AFTER `message`;

-- 5) Data clean-up so constraints can be added
DELETE FROM `post` WHERE TRIM(`content`) = '' AND (`photo` IS NULL OR `photo` = '');
UPDATE `post` SET `privacy` = 'public' WHERE `privacy` = '';
UPDATE `post` SET `category` = 'general' WHERE `category` IS NULL OR `category` = '';
DELETE FROM `comments` WHERE `post_id` IS NULL OR `post_id` NOT IN (SELECT `post_id` FROM `post`);
DELETE FROM `likes` WHERE `post_id` IS NULL OR `post_id` NOT IN (SELECT `post_id` FROM `post`);
DELETE ep1 FROM `event_participants` ep1
  JOIN `event_participants` ep2 ON ep1.`event_id` = ep2.`event_id` AND ep1.`participant_id` = ep2.`participant_id`
  AND ep1.`event_participant_id` > ep2.`event_participant_id`;
UPDATE `events` SET `name` = `title` WHERE (`name` = '' OR `name` IS NULL) AND `title` IS NOT NULL;
ALTER TABLE `events` MODIFY `name` varchar(255) NOT NULL DEFAULT '';

-- 6) Integrity: deleting a post removes its likes/comments; one RSVP per user per event
ALTER TABLE `comments` ADD CONSTRAINT `comments_post_fk` FOREIGN KEY (`post_id`) REFERENCES `post` (`post_id`) ON DELETE CASCADE;
ALTER TABLE `likes` ADD CONSTRAINT `likes_post_fk` FOREIGN KEY (`post_id`) REFERENCES `post` (`post_id`) ON DELETE CASCADE;
ALTER TABLE `event_participants` ADD UNIQUE KEY `uniq_event_user` (`event_id`,`participant_id`);
ALTER TABLE `daily_planner` ADD KEY `idx_planner_user` (`user_id`,`deadline`);

-- 7) A ready-to-use demo account  (email: demo@parentconnect.test  /  password: Demo@1234)
--    Delete it before going public:  DELETE FROM user WHERE email = 'demo@parentconnect.test';
INSERT IGNORE INTO `user` (`username`, `password`, `email`, `first_name`, `last_name`, `bio`, `location`, `children_details`)
VALUES ('demo', '$2y$10$yBbltTrvyBvMH3CIFF2bv.42W6jTW.FGBSTmMi2vVwu7iwTLPcUOG', 'demo@parentconnect.test', 'Demo', 'Parent', 'Hi! This is a demo account for trying out ParentConnect.', 'Dhaka', 'One child, age 6, on the autism spectrum');
