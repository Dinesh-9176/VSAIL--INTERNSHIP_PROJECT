# User Registration, Login and Profile System

This is a user authentication system built using PHP, MySQL, MongoDB, and Redis. The project allows users to register, login, and manage their profile.

## Demo Video

Watch the working demo here: https://drive.google.com/drive/folders/1jcFiGq0ofruoaF011rWDVApbvxUq9H5_?usp=sharing

## Tech Stack

- Frontend: HTML, CSS, JavaScript, jQuery, Bootstrap
- Backend: PHP
- Databases: MySQL (for auth), MongoDB (for profiles), Redis (for sessions)

## Folder Structure

```
vsail/
    assets/
    css/
        style.css
    js/
        login.js
        profile.js
        register.js
    php/
        config.php
        login.php
        profile.php
        register.php
    index.html
    login.html
    profile.html
    register.html
```

## How to Setup

### Step 1: Install Required Software

First you need to install XAMPP which includes Apache, MySQL and PHP. Download it from apachefriends.org.

Then install MongoDB from mongodb.com and Redis from redis.io.

### Step 2: Install PHP Extensions

You need mongodb and redis extensions for PHP. Download the DLL files from windows.php.net based on your PHP version. Copy them to xampp/php/ext folder and add these lines to php.ini:

```
extension=mongodb
extension=redis
```

Then restart Apache.

### Step 3: Setup MySQL Database

Open phpMyAdmin at localhost/phpmyadmin. Create a new database called vsail_db and run this query to create the users table:

```sql
CREATE DATABASE vsail_db;
USE vsail_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    is_active TINYINT(1) DEFAULT 1
);
```

### Step 4: Start Services

Make sure Apache and MySQL are running in XAMPP. MongoDB should be running as a Windows service. Start Redis by running redis-server.

### Step 5: Copy Project Files

Copy the vsail folder to C:/xampp/htdocs/

### Step 6: Access the Application

Open your browser and go to localhost/vsail

## How to Test

1. Go to the home page and click Create Account
2. Fill in the registration form with your details
3. After successful registration, go to Sign In page
4. Login with your username and password
5. You will be redirected to the Profile page
6. Update your profile details like age, date of birth, contact number etc
7. Click Save to update your profile

## Where Data is Stored

MySQL stores the user registration data like username, email, password hash, first name and last name. You can view this in phpMyAdmin under vsail_db database, users table.

MongoDB stores the profile details like age, date of birth, contact number, address, city, country and bio. You can use MongoDB Compass to view this data.

Redis stores the session information when a user logs in. The session contains user details and expires after 24 hours.

## Features

- User registration with validation
- User login with session management
- Profile viewing and updating
- Password hashing with bcrypt
- Prepared statements for SQL queries
- Session storage using localStorage and Redis
- Responsive design with Bootstrap

## Configuration

Database settings are in php/config.php. Update the MySQL password if you have one set. Default settings work with standard XAMPP installation.
