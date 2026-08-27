# Test Users

All users sign in at `login.php` with their **Login ID** and **Password**.

| # | Login ID | Name | Role | Password | Can do |
|---|----------|------|------|----------|--------|
| 1 | `admin` | Administrator | Admin | `admin123` | Add users (students/instructors), departments, blocks, courses/subjects, schedules; enroll students; view attendance & grades (read-only) and reports |
| 2 | `EMP-2026-0001` | Maria Santos | Instructor | `password123` | Record attendance & grades for her sections (Database Management, Systems Analysis & Design); view her subjects. **Cannot** add students or users |
| 3 | `EMP-2026-0003` | Noel Fusingan | Instructor | `password123` | Record attendance & grades for his sections (Web Development 1 & 2, Capstone Project 1); view his subjects |
| 4 | `STU-2026-0001` | Juan Dela Cruz | Student | `password123` | BSIT 1st Year - Block 1 (BSIT 1A), **Regular**. View his own grades, attendance, subjects, and final grades. Enrolled in Fundamentals of Programming (Inst. Mitch Ramos) and Web Development 1 (Inst. Noel Fusingan). No grades recorded yet — his instructor must use the grade score sheet first |
| 5 | `STU-2026-0006` | Francis Dela Cruz | Student | `password123` | BSIT 1st Year - Block 2, **Irregular** — also takes subjects from other year levels |

## Seeded demo data

`database/seed.php` wipes and re-seeds a block-based dataset (run: `php database/seed.php`):

- **3 departments:** BSIT, BSBA, BSED (each with a Bachelor program)
- **18 class blocks:** 2 blocks for 1st–2nd years, 1 block for 3rd–4th years, per department
- **24 subjects** (2 per department per year), **36 schedules** (sections) tied to blocks, **7 instructors**, **90 students** (75 regular / 15 irregular), **195 enrollments** (irregulars also take subjects from other year levels)

## Notes

- **Default password** for every seeded student/instructor account is `password123`.
- After the first login, any user can change their own password via
  **Change Password** (top-right of the app).
- Admins can reset a user's password back to `password123` or deactivate/delete
  an account from the **Users** page.
- Login IDs are auto-generated: `STU-YYYY-####` for students and `EMP-YYYY-####`
  for instructors.