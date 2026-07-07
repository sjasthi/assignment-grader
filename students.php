<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

$message = "";

/* ADD STUDENT */
if (isset($_POST['add_student'])) {
    $class_id = $_POST['class_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);

    if (!empty($class_id) && !empty($first_name) && !empty($last_name) && !empty($email)) {
        $sql = "INSERT INTO students (class_id, first_name, last_name, email)
                VALUES ('$class_id', '$first_name', '$last_name', '$email')";

        if (mysqli_query($conn, $sql)) {
            $message = "Student added successfully.";
        } else {
            $message = "Error adding student.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* DELETE STUDENT */
if (isset($_GET['delete'])) {
    $student_id = $_GET['delete'];

    $sql = "DELETE FROM students WHERE student_id = $student_id";

    if (mysqli_query($conn, $sql)) {
        $message = "Student deleted successfully.";
    } else {
        $message = "Error deleting student.";
    }
}

/* GET STUDENT FOR EDIT */
$edit_student = null;

if (isset($_GET['edit'])) {
    $student_id = $_GET['edit'];

    $edit_result = mysqli_query($conn, "SELECT * FROM students WHERE student_id = $student_id");
    $edit_student = mysqli_fetch_assoc($edit_result);
}

/* UPDATE STUDENT */
if (isset($_POST['update_student'])) {
    $student_id = $_POST['student_id'];
    $class_id = $_POST['class_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);

    if (!empty($class_id) && !empty($first_name) && !empty($last_name) && !empty($email)) {
        $sql = "UPDATE students
                SET class_id = '$class_id',
                    first_name = '$first_name',
                    last_name = '$last_name',
                    email = '$email'
                WHERE student_id = $student_id";

        if (mysqli_query($conn, $sql)) {
            $message = "Student updated successfully.";
            $edit_student = null;
        } else {
            $message = "Error updating student.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* GET CLASSES */
$classes = mysqli_query($conn, "SELECT * FROM classes");

/* GET STUDENTS */
$students = mysqli_query($conn, "
    SELECT students.student_id,
           students.class_id,
           students.first_name,
           students.last_name,
           students.email,
           classes.class_name
    FROM students
    LEFT JOIN classes
    ON students.class_id = classes.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="cs-header">
    <div class="cs-header-container">

        <a href="index.php" class="cs-logo">
            Assignment Grader
        </a>

        <nav class="cs-nav">
            <a href="index.php">Home</a>
            <a href="classes.php">Classes</a>
            <a href="students.php">Students</a>
            <a href="assignments.php">Assignments</a>
            <a href="rubrics.php">Rubrics</a>
            <a href="submissions.php">Submissions</a>
            <a href="grades.php">Grades</a>
        </nav>

        <a href="#" class="cs-header-button">Login</a>

    </div>
</header>

<section class="page-section">
    <h2>Manage Students</h2>
    <p>Add, view, edit, and delete students in the Assignment Grader System.</p>

    <?php if (!empty($message)) { ?>
        <p><strong><?php echo $message; ?></strong></p>
    <?php } ?>

    <div class="form-box">

        <?php if ($edit_student) { ?>

            <h3>Edit Student</h3>

            <form method="POST">

                <input type="hidden" name="student_id" value="<?php echo $edit_student['student_id']; ?>">

                <label>Class</label>
                <select name="class_id" required>
                    <option value="">Select Class</option>

                    <?php while ($class = mysqli_fetch_assoc($classes)) { ?>
                        <option 
                            value="<?php echo $class['id']; ?>"
                            <?php if ($class['id'] == $edit_student['class_id']) echo "selected"; ?>
                        >
                            <?php echo $class['class_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>First Name</label>
                <input 
                    type="text" 
                    name="first_name" 
                    value="<?php echo $edit_student['first_name']; ?>" 
                    required
                >

                <label>Last Name</label>
                <input 
                    type="text" 
                    name="last_name" 
                    value="<?php echo $edit_student['last_name']; ?>" 
                    required
                >

                <label>Email</label>
                <input 
                    type="email" 
                    name="email" 
                    value="<?php echo $edit_student['email']; ?>" 
                    required
                >

                <button type="submit" name="update_student">
                    Update Student
                </button>

                <a href="students.php">Cancel</a>

            </form>

        <?php } else { ?>

            <h3>Add New Student</h3>

            <form method="POST">

                <label>Class</label>
                <select name="class_id" required>
                    <option value="">Select Class</option>

                    <?php while ($class = mysqli_fetch_assoc($classes)) { ?>
                        <option value="<?php echo $class['id']; ?>">
                            <?php echo $class['class_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>First Name</label>
                <input type="text" name="first_name" placeholder="Example: Jacob" required>

                <label>Last Name</label>
                <input type="text" name="last_name" placeholder="Example: Vang" required>

                <label>Email</label>
                <input type="email" name="email" placeholder="Example: student@email.com" required>

                <button type="submit" name="add_student">
                    Add Student
                </button>

            </form>

        <?php } ?>

    </div>

    <div class="table-box">
        <h3>Student List</h3>

        <table>
            <tr>
                <th>Class</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($students)) { ?>
                <tr>
                    <td><?php echo $row['class_name']; ?></td>
                    <td><?php echo $row['first_name']; ?></td>
                    <td><?php echo $row['last_name']; ?></td>
                    <td><?php echo $row['email']; ?></td>
                    <td>
                        <a href="students.php?edit=<?php echo $row['student_id']; ?>">Edit</a>
                        |
                        <a 
                            href="students.php?delete=<?php echo $row['student_id']; ?>"
                            onclick="return confirm('Are you sure you want to delete this student?');"
                        >
                            Delete
                        </a>
                    </td>
                </tr>
            <?php } ?>

        </table>
    </div>
</section>

<footer id="cs-footer-1292">
    <div class="cs-container">      

        <div class="cs-logo-group">
            <a class="cs-footer-logo" href="index.php">
                Assignment Grader
            </a>

            <p class="cs-text">
                A web app for managing classes, students, assignments, rubrics, submissions, and feedback.
            </p>

            <a href="mailto:info@assignmentgrader.com" class="cs-link">
                info@assignmentgrader.com
            </a>
        </div>

        <ul class="cs-footer-nav">
            <li><span class="cs-footer-header">System</span></li>
            <li><a class="cs-footer-nav-link" href="classes.php">Classes</a></li>
            <li><a class="cs-footer-nav-link" href="students.php">Students</a></li>
            <li><a class="cs-footer-nav-link" href="assignments.php">Assignments</a></li>
            <li><a class="cs-footer-nav-link" href="rubrics.php">Rubrics</a></li>
        </ul>

        <ul class="cs-footer-nav">
            <li><span class="cs-footer-header">Project</span></li>
            <li><a class="cs-footer-nav-link" href="index.php">Home</a></li>
            <li><a class="cs-footer-nav-link" href="#">ICS 499</a></li>
            <li><a class="cs-footer-nav-link" href="#">Capstone</a></li>
            <li><a class="cs-footer-nav-link" href="#">Learn and Help</a></li>
        </ul>

        <ul class="cs-footer-nav">
            <li><span class="cs-footer-header">Team</span></li>
            <li><a class="cs-footer-nav-link" href="#">Jacob</a></li>
            <li><a class="cs-footer-nav-link" href="#">Zuhaib</a></li>
            <li><a class="cs-footer-nav-link" href="#">Suhayb</a></li>
        </ul>

    </div>

    <div class="cs-bottom">
        <span class="cs-copyright">
            Copyright © 2026.
            <a class="cs-copyright-link" href="index.php">Assignment Grader.</a>
            All Rights Reserved.
        </span>

        <a href="#" class="cs-copyright-link">Terms of Service</a>
        <a href="#" class="cs-copyright-link">Privacy Policy</a>
    </div>

    <div class="cs-floater" aria-hidden="true"></div>
</footer>

</body>
</html>