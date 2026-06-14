# Hospital Management System (PHP + JSON Storage + Bootstrap 5)

Role-based hospital management web app with:
- Admin
- Doctor
- Patient

## Features

- Secure login with `password_hash` / `password_verify`
- Patient self-registration
- Role-based dashboards and access control
- Admin:
  - Manage doctors (add/edit/delete)
  - Store doctor phone and address
  - Set doctor min/max working schedule time
  - Set doctor min/max patient limit per day
  - Manage patients (edit/delete)
  - Manage appointments and status
  - Add appointments directly
  - Dashboard statistics
- Doctor:
  - View assigned appointments
  - Accept/Reject/Complete appointments
  - Add prescription or notes
  - View schedule
  - Update/Delete own account
- Patient:
  - Register and login
  - View doctors
  - Book appointments
  - Track appointment status
  - View prescriptions
  - Update/Delete own account

## Folder Structure

```text
hospital/
├─ admin/
├─ doctor/
├─ patient/
├─ assets/
│  ├─ css/
│  └─ js/
├─ config/
├─ includes/
├─ database/
│  └─ hospital.sql
├─ bootstrap.php
├─ index.php
├─ login.php
├─ register.php
├─ logout.php
└─ unauthorized.php
```

## Setup

1. Place project in:
   - `c:\xampp\htdocs\hospital`
2. Start Apache in XAMPP and open:
   - `http://localhost/hospital`

### JSON Storage

- Data is stored in `storage/jsondb/hospital.json`.
- No MySQL database is required.

## Default Admin Login

- Email: `admin@hospital.com`
- Password: `Admin@123`

## Notes

- `BASE_URL` is configured as `/hospital` in `config/config.php`.
- Doctor and patient records are linked to login users by email.
