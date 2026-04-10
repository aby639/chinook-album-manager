# Chinook Album Manager
A PHP and MySQL web application for managing album records in the Chinook database.

This project was built as part of a server-side web development assignment. It allows a user to view, create, update, and delete album records while also working with related data from the `artists` and `tracks` tables.

## Features

- View albums with related artist information
- Search by album title or artist name
- Open a details page for a selected album
- Insert a new album with artist and track data
- Update album, artist, and track information
- Delete an album and its related tracks
- Show success messages after create, update, and delete actions

## Technologies Used

- PHP
- MySQL
- HTML
- CSS
- JavaScript
- XAMPP

## Project Structure

- `index.php` - homepage with search, dashboard, and record list
- `create.php` - add a new album and related records
- `details.php` - show one album with its tracks
- `update.php` - edit album, artist, and track data
- `delete.php` - delete an album and related tracks
- `db.php` - database connection file
- `style.css` - project styling
- `user_documentation_final.pdf` - user documentation

## Database

This project uses the Chinook database.

- Database file: `chinook.sql`
- Database name: `chinook`

## How to Run the Project

1. Install and open XAMPP.
2. Start `Apache` and `MySQL`.
3. Import the `chinook.sql` file into phpMyAdmin.
4. Place the project folder inside `htdocs`.
5. Open the project in the browser:

```text
http://localhost/server_side_development/chinook_project/
```

## Notes

- This project uses the provided Chinook sample database.
- IDs for some inserted records are generated in PHP using `MAX(...) + 1` because of the structure of the database used in this coursework.
- This repository is for educational purposes.
