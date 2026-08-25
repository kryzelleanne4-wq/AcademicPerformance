# Test Users

All users sign in at `login.php` with their **Login ID** and **Password**.

| # | Login ID | Name | Role | Password | Can do |
|---|----------|------|------|----------|--------|
| 1 | `admin` | Administrator | Admin | `admin123` | Add users (students/instructors), departments, courses/subjects, schedules; enroll students; record attendance & grades; view reports |
| 2 | `EMP-2026-0001` | Maria Santos | Teacher | `password123` | Record attendance & grades for her sections; view her subjects. **Cannot** add students or users |
| 3 | `STU-2026-0001` | Juan Dela Cruz | Student | `password123` | View his own grades, attendance, subjects, and final grades. Enrolled in CS101 (Intro to Programming) with one grade (92 / A) and one attendance record |

> **Legacy record:** `Kryzelle Anne` exists in the students table (student ID `1`) but has no
> login account yet. To give her one, delete and re-add her on the **Users** page — a new
> `STU-…` login ID and the default password are generated automatically.

## Notes

- **Default password** for every newly created student/instructor account is `password123`.
- After the first login, any user can change their own password via
  **Change Password** (top-right of the app).
- Admins can reset a user's password back to `password123` or deactivate/delete
  an account from the **Users** page.
- Login IDs are auto-generated: `STU-YYYY-####` for students and `EMP-YYYY-####`
  for instructors.
