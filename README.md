# Rotary Club Management System

A full-stack web-based Rotary Club Management System developed using PHP and MySQL to streamline club operations, member coordination, event management, and administrative workflows.

---

## Overview

This project was designed to digitize Rotary Club activities and reduce manual management processes by providing a centralized platform for members and administrators.

The system supports:
- Member registration and approval
- Admin dashboards
- Event management
- Task assignment
- Feedback handling
- Password reset with OTP verification
- Notification workflows

---

## Features

### Member Module
- Member Registration
- Login Authentication
- Profile Management
- View Assigned Tasks
- Accept/Reject Tasks
- Submit Feedback
- Event Notifications

### Admin Module
- Approve/Reject Registrations
- Manage Members
- Create and Manage Events
- Assign Tasks to Members
- Monitor Feedback
- Dashboard Analytics

### Authentication & Security
- Password Reset via OTP
- Email Verification Workflow
- Session Management
- Password Hashing

---

## Tech Stack

| Technology | Purpose |
|------------|---------|
| PHP | Backend Development |
| MySQL | Database Management |
| HTML/CSS | Frontend UI |
| Bootstrap | Responsive Design |
| JavaScript | Client-side Interaction |
| PHPMailer | Email & OTP Services |
| XAMPP | Local Development Server |

---

## Project Structure

```bash
club-management/
│
├── includes/        # Database and configuration files
├── src/             # PHPMailer source files
├── templates/       # Frontend pages and dashboards
├── uploads/         # Uploaded documents/files
├── assets/          # CSS, JS, images
└── README.md
```

---

## Modules Included

- Member Dashboard
- Admin Dashboard
- Event Management
- Work Assignment System
- Feedback System
- OTP-based Password Recovery

---

## Database

The system uses MySQL with tables such as:
- members
- admin
- pending_approval
- events
- workassignment
- feedback

---

## How to Run the Project

1. Install XAMPP
2. Start Apache and MySQL
3. Import the database into phpMyAdmin
4. Place the project folder inside:
   ```bash
   htdocs/
   ```
5. Open browser:
   ```bash
   http://localhost/club-management
   ```

---

## Future Enhancements

- Real-time notifications
- Attendance tracking
- Payment integration
- Role-based access control
- Mobile responsive improvements

---

## Author

**Varshini Hegde**

GitHub:
https://github.com/Varshinihegde

---

## License

This project is developed for educational and academic purposes.
