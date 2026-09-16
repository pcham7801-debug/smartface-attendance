# SmartFace Attendance Monitoring System with Face Recognition

**SmartFace Attendance Monitoring System** is an AI-powered, browser camera-based attendance monitoring system for schools and colleges built using **PHP, MySQL, HTML5, CSS3, JavaScript, Bootstrap 5**, and **face-api.js**.

---

## Key Features

### 1. Dual User Roles & Role-Based Access Control
- **ADMIN PORTAL**:
  - Dashboard analytics & charts (Total Students, Subjects, Present, Late, Absent Today, Chart.js trends).
  - Student Management (Add, Edit, Delete, View Profile, Reset Password, Activate/Deactivate Account).
  - Subject Management (Code, Name, Schedule, Room, Instructor, Semester, School Year).
  - Student-Subject Enrollment (Assign students to subjects for subject-wise attendance monitoring).
  - Attendance Logs & Manual Attendance Override (Add manual check-in with audit logging).
  - Reports Generator (Daily, Weekly, Monthly, Subject, Student reports with CSV export & Printable PDF format).
  - Admin Face Registration Tool (Assist students in scanning face biometrics).
  - System Audit Logs & Settings (Configurable grace period, school name, admin password update).
- **STUDENT PORTAL**:
  - Personal Dashboard with live digital clock, profile details, attendance rate, and enrolled subject progress bars.
  - Face Registration camera interface (scans and saves 128-dimensional facial descriptor vector).
  - Face Attendance Sign In ("Sign In with Face" camera scanner, live visual status feedback: *Face Detected*, *Verifying...*, *Face Verified*).
  - Face Attendance Sign Out (Logs sign-out time for active subject sessions).
  - Attendance History with filters (Subject, Status, Date range).

### 2. Biometric Facial Recognition
- Real-time client-side face landmark & descriptor extraction using `face-api.js`.
- 128-float facial embeddings stored securely in MySQL as JSON arrays.
- Attendance verification comparing live camera feed against registered face encoding using Euclidean distance thresholding.

### 3. Schedule-Aware Attendance Logic & Protection
- Attendance is recorded per **Student + Subject + Date**.
- Automatically marks status as **Present** or **Late** based on class start time and configurable grace period (e.g. 15 minutes).
- **Duplicate Attendance Protection**: Prevents multiple sign-ins for the same student and subject when an active session already exists today.

---

## Project Structure

```text
smartface-attendance/
├── config/
│   ├── database.php
│   ├── config.php
│   └── auth.php
├── auth/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   └── forgot_password.php
├── admin/
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│   ├── dashboard.php
│   ├── students.php
│   ├── student_view.php
│   ├── subjects.php
│   ├── enrollment.php
│   ├── attendance.php
│   ├── reports.php
│   ├── face_registration.php
│   ├── admins.php
│   ├── audit_logs.php
│   └── settings.php
├── student/
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│   ├── dashboard.php
│   ├── profile.php
│   ├── subjects.php
│   ├── attendance.php
│   ├── attendance_history.php
│   ├── face_attendance.php
│   └── signout.php
├── api/
│   ├── face_register.php
│   ├── face_verify.php
│   ├── attendance_in.php
│   ├── attendance_out.php
│   ├── get_enrolled_subjects.php
│   └── export_report.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       ├── app.js
│       ├── camera.js
│       └── face-recognition.js
├── uploads/
│   └── profiles/
├── database/
│   └── attendance.sql
├── index.php
└── README.md
```

---

## Installation & Setup Instructions (XAMPP)

### Prerequisites
- **XAMPP** (PHP 7.4 / 8.x, Apache, MySQL / MariaDB).
- Web browser (Chrome, Edge, Firefox) with webcam access permission.

### Step 1: Copy Project Files
Place the project directory into your XAMPP `htdocs` folder:
```text
C:\xampp\htdocs\Attendance Monitoring
```
(or `smartface-attendance`).

### Step 2: Database Setup in phpMyAdmin
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open your browser and navigate to `http://localhost/phpmyadmin`.
3. Click on the **Import** tab.
4. Choose the `database/attendance.sql` file located in the project directory.
5. Click **Import** (or **Go**). The `smartface_attendance` database and tables will be created automatically.

### Step 3: Default Login Credentials

#### Administrator Account:
- **Username**: `admin`
- **Password**: `admin123`

#### Sample Student Account:
- **Username**: `student`
- **Password**: `student123`
- **Student ID**: `2026-0001`

---

## Web Camera Permissions & Face Recognition Notes
1. Modern browsers require **localhost** or **HTTPS** to grant camera access (`navigator.mediaDevices.getUserMedia`). Ensure you run the application via `http://localhost/...`.
2. Allow webcam permission when prompted by the browser.
3. If no face is registered yet for a student, navigate to **My Profile** -> **Register My Face** or use the **Admin Face Registration Tool**.

---

## Security Implementation
- `password_hash()` and `password_verify()` with bcrypt encryption.
- PDO prepared SQL statements preventing SQL Injection attacks.
- Anti-CSRF token verification on state-changing POST requests.
- Session-based Role Authorization (`requireAdmin()`, `requireStudent()`).
- HTML output escaping (`e()`) guarding against Cross-Site Scripting (XSS).
- System activity audit logging (`audit_logs`).
