-- Auto-fix domain for Docker local development
UPDATE `settings` SET `value` = 'localhost:8080' WHERE `name` = 'domains';
