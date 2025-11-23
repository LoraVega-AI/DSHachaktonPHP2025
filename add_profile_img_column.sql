-- Add profile_img column to users table
ALTER TABLE users 
ADD COLUMN profile_img VARCHAR(500) DEFAULT NULL AFTER email,
ADD COLUMN bio TEXT DEFAULT NULL AFTER profile_img;

-- Update existing users with default avatar based on their username
UPDATE users 
SET profile_img = CONCAT('https://ui-avatars.com/api/?name=', REPLACE(username, ' ', '+'), '&background=3b82f6&color=fff&size=200')
WHERE profile_img IS NULL;

