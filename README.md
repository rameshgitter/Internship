# College Management System

A web-based ERP for college administration, supporting students, professors, HOD, and staff. Features include semester registration, subject management, verification workflows, and role-based dashboards.

## Features

- **Role-based authentication** (student, professor, HOD, staff)
- **Student dashboard**: semester registration, registration history, PDF download, marks view
- **Professor dashboard**: view assigned subjects, marks upload (placeholder)
- **HOD dashboard**: subject/semester creation, assign staff for verification, view all students/staff/subjects, statistics
- **Staff dashboard**: verification assignments, update verification status
- **Password management**: force password change on first login
- **PDF generation** for registration forms
- **Session management** and security

## Project Structure

- `login.php` — Login system
- `dashboard.php` — Student dashboard
- `professor_dashboard.php` — Professor dashboard
- `hod_dashboard.php` — HOD dashboard
- `staff_dashboard.php` — Staff dashboard
- `profile.php` — User profile management
- `semester_registration.php` — Student semester registration
- `change_password.php` — Password change
- `db_config.php` — Database connection
- `extract_marksheet_data.py` — Marksheet PDF data extraction
- `css/` — Stylesheets
- `uploads/` — Uploaded PDFs and registration forms
- `clean_database_setup.sql` — Full database schema

## Database

- See `clean_database_setup.sql` for schema
- Main tables: `index` (user credentials), `student_info`, `staff_info`, `hod`, `subjects_pool`, `semester_registration`, `subjects_offered`, `verification_assignments`, `student_marks`

## Setup Instructions

1. **Clone the repository**
2. **Install dependencies**
   - PHP 8+
   - MySQL
   - Python 3 (for marksheet extraction)
   - Required Python packages: `pip install PyPDF2 pytesseract Pillow pdf2image`
3. **Import the database**
   - Run: `mysql -u <user> -p < clean_database_setup.sql`
4. **Configure database connection**
   - Edit `db_config.php` with your DB credentials
5. **Set up uploads directory**
   - Ensure `uploads/` and subfolders are writable by the web server
6. **Start the PHP server**
   - `php -S localhost:8000`
7. **Access the app**
   - Open `http://localhost:8000` in your browser

## How to Run the ERP Application

### Prerequisites

- PHP 8 or higher
- MySQL server
- Python 3 (for marksheet extraction)
- Required Python packages: `pip install PyPDF2 pytesseract Pillow pdf2image`

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd <repository-folder>
   ```
2. **Install dependencies**
   - Ensure PHP and MySQL are installed on your system.
   - Install Python packages (for marksheet extraction):
     ```bash
     pip install PyPDF2 pytesseract Pillow pdf2image
     ```
3. **Import the database**
   - Run the following command to set up the database schema:
     ```bash
     mysql -u <user> -p < clean_database_setup.sql
     ```
4. **Configure database connection**
   - Edit `db_config.php` and update your MySQL credentials.
5. **Set up uploads directory**
   - Ensure `uploads/` and its subfolders are writable by the web server:
     ```bash
     chmod -R 775 uploads/
     ```
6. **Start the PHP development server**
   - From the project root, run:
     ```bash
     php -S localhost:8000
     ```
7. **Access the application**
   - Open your browser and go to: [http://localhost:8000](http://localhost:8000)

### Additional Notes

- For multi-user testing, use different browsers or incognito windows.
- Registration PDFs are saved in `uploads/registrations/`.
- Marksheet extraction uses OCR if needed (see `extract_marksheet_data.py`).

## Default Test Credentials

- HOD: `HOD001` / `HOD001`
- Professor: `PROF001` / `PROF001`
- Staff: `STAFF001` / `STAFF001`
- Student: `STU001` / `STU001`, `STU002` / `STU002`

## SQL Queries Used

See `IMPLEMENTATION_SUMMARY.md` for a full list of SQL queries and where they are used in the codebase.

## Notes

- For multi-user testing, use different browsers/incognito windows (sessions are per browser)
- PDF registration forms are generated and stored in `uploads/registrations/`
- Marksheet extraction uses OCR if needed (see `extract_marksheet_data.py`)

## License

This project is for educational use. Adapt as needed for your institution.
