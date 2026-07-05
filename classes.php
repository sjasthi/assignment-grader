<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

$message = "";

/* ADD CLASS */
if (isset($_POST['add_class'])) {
    $class_name = trim($_POST['class_name']);
    $instructor_name = trim($_POST['instructor_name']);

    if (!empty($class_name) && !empty($instructor_name)) {
        $sql = "INSERT INTO classes (class_name, instructor_name)
                VALUES ('$class_name', '$instructor_name')";

        if (mysqli_query($conn, $sql)) {
            $message = "Class added successfully.";
        } else {
            $message = "Error adding class.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* DELETE CLASS */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // First delete students in this class
    mysqli_query($conn, "DELETE FROM students WHERE class_id = $id");

    // Then delete the class
    $sql = "DELETE FROM classes WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $message = "Class and assigned students deleted successfully.";
    } else {
        $message = "Error deleting class.";
    }
}

/* GET CLASS FOR EDIT */
$edit_class = null;

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];

    $edit_result = mysqli_query($conn, "SELECT * FROM classes WHERE id = $id");
    $edit_class = mysqli_fetch_assoc($edit_result);
}

/* UPDATE CLASS */
if (isset($_POST['update_class'])) {
    $id = $_POST['id'];
    $class_name = trim($_POST['class_name']);
    $instructor_name = trim($_POST['instructor_name']);

    if (!empty($class_name) && !empty($instructor_name)) {
        $sql = "UPDATE classes 
                SET class_name = '$class_name',
                    instructor_name = '$instructor_name'
                WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            $message = "Class updated successfully.";
            $edit_class = null;
        } else {
            $message = "Error updating class.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* DISPLAY CLASSES */
$result = mysqli_query($conn, "SELECT * FROM classes");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes</title>
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
        </nav>

        <a href="#" class="cs-header-button">Login</a>

    </div>
</header>

<section class="page-section">

    <h2>Manage Classes</h2>

    <p>
        Create, view, edit, and delete classes for the Assignment Grader System.
    </p>

    <?php if (!empty($message)) { ?>
        <p><strong><?php echo $message; ?></strong></p>
    <?php } ?>

    <div class="form-box">

        <?php if ($edit_class) { ?>

            <h3>Edit Class</h3>

            <form method="POST">

                <input type="hidden" name="id" value="<?php echo $edit_class['id']; ?>">

                <label>Class Name</label>
                <input
                    type="text"
                    name="class_name"
                    value="<?php echo $edit_class['class_name']; ?>"
                    required
                >

                <label>Instructor</label>
                <input
                    type="text"
                    name="instructor_name"
                    value="<?php echo $edit_class['instructor_name']; ?>"
                    required
                >

                <button type="submit" name="update_class">
                    Update Class
                </button>

                <a href="classes.php">Cancel</a>

            </form>

        <?php } else { ?>

            <h3>Add New Class</h3>

            <form method="POST">

                <label>Class Name</label>
                <input
                    type="text"
                    name="class_name"
                    placeholder="Example: ICS 499"
                    required
                >

                <label>Instructor</label>
                <input
                    type="text"
                    name="instructor_name"
                    placeholder="Example: Professor Jasthi"
                    required
                >

                <button type="submit" name="add_class">
                    Add Class
                </button>

            </form>

        <?php } ?>

    </div>

    <div class="table-box">

        <h3>Class List</h3>

        <table>

            <tr>
                <th>Class Name</th>
                <th>Instructor</th>
                <th>Actions</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                <tr>
                    <td><?php echo $row['class_name']; ?></td>
                    <td><?php echo $row['instructor_name']; ?></td>
                    <td>
                        <a href="classes.php?edit=<?php echo $row['id']; ?>">Edit</a>
                        |
                        <a 
                            href="classes.php?delete=<?php echo $row['id']; ?>"
                            onclick="return confirm('Are you sure you want to delete this class?');"
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