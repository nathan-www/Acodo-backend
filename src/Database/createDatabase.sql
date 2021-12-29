CREATE TABLE accounts(
	user_id BIGINT PRIMARY KEY,
	username VARCHAR(20),
	email VARCHAR(50),
	password_hash VARCHAR(255),
	registration_ip VARCHAR(255),
	email_verified VARCHAR(5),
	verification_token VARCHAR(255),
	reset_token VARCHAR(255),
	last_verification_email_sent VARCHAR(255),
	last_reset_email_sent VARCHAR(255),
	account_active VARCHAR(5),
	registration_timestamp BIGINT,
	xp BIGINT,
	show_email VARCHAR(5),
	twitter VARCHAR(16),
	linkedin VARCHAR(101),
	github VARCHAR(40),
	website VARCHAR(50),
	location VARCHAR(50),
	streak_last_timestamp VARCHAR(255),
	streak_days VARCHAR(255),
	last_active_timestamp VARCHAR(255)
);

CREATE TABLE sessions(
	session_id VARCHAR(255) PRIMARY KEY,
	user_id BIGINT,
	created VARCHAR(255),
	last_activity VARCHAR(255),
	ip VARCHAR(255),
	ip_location VARCHAR(255),
	device VARCHAR(255),
	token VARCHAR(255)
);

CREATE TABLE login_requests(
	user_id	BIGINT,
	timestamp BIGINT,
	ip VARCHAR(255),
	PRIMARY KEY (user_id, timestamp)
);

CREATE TABLE courses(
	course_id BIGINT PRIMARY KEY,
	course_slug VARCHAR(255),
	title VARCHAR(101),
	description MEDIUMTEXT,
	thumbnail VARCHAR(255),
	total_xp BIGINT,
	difficulty VARCHAR(15),
	duration_hours BIGINT
);

CREATE TABLE course_languages(
	course_id BIGINT,
	language VARCHAR(255),
	PRIMARY KEY (course_id, language)
);

CREATE TABLE course_authors(
	course_id BIGINT,
	user_id VARCHAR(255),
	PRIMARY KEY (course_id, user_id)
);

CREATE TABLE course_enrollments(
	course_id BIGINT,
	user_id VARCHAR(255),
	PRIMARY KEY (course_id, user_id)
);

CREATE TABLE chapters(
	chapter_id BIGINT,
	chapter_slug VARCHAR(255),
	course_id BIGINT,
	title VARCHAR(255),
	description MEDIUMTEXT,
	PRIMARY KEY (course_id, chapter_id)
);

CREATE TABLE levels(
	level_id BIGINT PRIMARY KEY,
	level_slug VARCHAR(101),
	chapter_id BIGINT,
	course_id BIGINT,
	title VARCHAR(255),
	difficulty VARCHAR(255),
	xp BIGINT,
	language VARCHAR(255),
	brief LONGTEXT,
	default_code LONGTEXT,
	test_code LONGTEXT,
	feedback_test VARCHAR(255)
);

CREATE TABLE unit_tests(
	level_id BIGINT,
	test_id BIGINT,
	title VARCHAR(255),
	subtitle VARCHAR(255),
	func VARCHAR(255),
	PRIMARY KEY (level_id, test_id)
);

CREATE TABLE level_forfeit(
	level_id BIGINT,
	user_id BIGINT,
	PRIMARY KEY (level_id, user_id)
);

CREATE TABLE level_complete(
	level_id BIGINT,
	user_id BIGINT,
	timestamp BIGINT,
	xp BIGINT,
	PRIMARY KEY (level_id, user_id)
);

CREATE TABLE level_drafts(
	level_id BIGINT,
	user_id BIGINT,
	code LONGTEXT,
	timestamp BIGINT,
	PRIMARY KEY (level_id, user_id)
);

CREATE TABLE level_authors(
	level_id BIGINT,
	user_id BIGINT,
	PRIMARY KEY (level_id, user_id)
);

CREATE TABLE solutions(
	level_id BIGINT,
	solution_id BIGINT,
	user_id BIGINT,
	timestamp BIGINT,
	code LONGTEXT,
	PRIMARY KEY (level_id, solution_id)
);

CREATE TABLE solution_votes(
	solution_id BIGINT,
	user_id BIGINT,
	vote VARCHAR(10),
	PRIMARY KEY (solution_id, user_id)
);

CREATE TABLE solution_badges(
	solution_id BIGINT,
	badge_id BIGINT,
	user_id BIGINT,
	PRIMARY KEY (solution_id, badge_id, user_id)
);

CREATE TABLE available_badges(
	badge_id BIGINT PRIMARY KEY,
	icon VARCHAR(255),
	name VARCHAR(255)
);

CREATE TABLE messages(
	message_id BIGINT PRIMARY KEY,
	level_id BIGINT,
	user_id BIGINT,
	message_content MEDIUMTEXT,
	sent_timestamp BIGINT,
	edited_timestamp BIGINT,
	changed_timestamp BIGINT,
	reply_to VARCHAR(255)
);

CREATE TABLE message_votes(
	message_id BIGINT,
	user_id BIGINT,
	vote VARCHAR(5),
	PRIMARY KEY (message_id, user_id)
);

CREATE TABLE message_tags(
	message_id BIGINT,
	user_id BIGINT,
	PRIMARY KEY (message_id, user_id)
);

CREATE TABLE leaderboards(
	leaderboard_id BIGINT PRIMARY KEY,
	name VARCHAR(255),
	measure VARCHAR(255)
);

CREATE TABLE leaderboard_items(
	leaderboard_id BIGINT,
	user_id BIGINT,
	val BIGINT,
	PRIMARY KEY (leaderboard_id, user_id)
);

CREATE TABLE notifications(
	notification_id BIGINT PRIMARY KEY,
	user_id BIGINT,
	timestamp BIGINT,
	notification_data MEDIUMTEXT
);
