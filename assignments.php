<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

$message = "";

/* ADD ASSIGNMENT */
if (isset($_POST['add_assignment'])) {
    $class_id = $_POST['class_id'];
    $assignment_name = trim($_POST['assignment_name']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $points = $_POST['points'];

    if (!empty($class_id) && !empty($assignment_name) && !empty($due_date) && !empty($points)) {
        $sql = "INSERT INTO assignments (class_id, assignment_name, description, due_date, points)
                VALUES ('$class_id', '$assignment_name', '$description', '$due_date', '$points')";

        if (mysqli_query($conn, $sql)) {
            $message = "Assignment added successfully.";
        } else {
            $message = "Error adding assignment.";
        }
    } else {
        $message = "Please fill in all required fields.";
    }
}

/* DELETE ASSIGNMENT */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $sql = "DELETE FROM assignments WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $message = "Assignment deleted successfully.";
    } else {
        $message = "Error deleting assignment.";
    }
}

/* GET ASSIGNMENT FOR EDIT */
$edit_assignment = null;

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];

    $edit_result = mysqli_query($conn, "SELECT * FROM assignments WHERE id = $id");
    $edit_assignment = mysqli_fetch_assoc($edit_result);
}

/* UPDATE ASSIGNMENT */
if (isset($_POST['update_assignment'])) {
    $id = $_POST['id'];
    $class_id = $_POST['class_id'];
    $assignment_name = trim($_POST['assignment_name']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $points = $_POST['points'];

    if (!empty($class_id) && !empty($assignment_name) && !empty($due_date) && !empty($points)) {
        $sql = "UPDATE assignments
                SET class_id = '$class_id',
                    assignment_name = '$assignment_name',
                    description = '$description',
                    due_date = '$due_date',
                    points = '$points'
                WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            $message = "Assignment updated successfully.";
            $edit_assignment = null;
        } else {
            $message = "Error updating assignment.";
        }
    } else {
        $message = "Please fill in all required fields.";
    }
}

/* GET CLASSES */
$classes = mysqli_query($conn, "SELECT * FROM classes");

/* GET ASSIGNMENTS */
$assignments = mysqli_query($conn, "
    SELECT assignments.id,
           assignments.class_id,
           assignments.assignment_name,
           assignments.description,
           assignments.due_date,
           assignments.points,
           classes.class_name
    FROM assignments
    LEFT JOIN classes
    ON assignments.class_id = classes.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="cs-header">
    <div class="cs-header-container">

        <a href="index.php" class="cs-logo">Assignment Grader</a>

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
    <h2>Manage Assignments</h2>
    <p>Create, view, edit, and delete assignments for each class.</p>

    <?php if (!empty($message)) { ?>
        <p><strong><?php echo $message; ?></strong></p>
    <?php } ?>

    <div class="form-box">

        <?php if ($edit_assignment) { ?>

            <h3>Edit Assignment</h3>

            <form method="POST">

                <input type="hidden" name="id" value="<?php echo $edit_assignment['id']; ?>">

                <label>Class</label>
                <select name="class_id" required>
                    <option value="">Select Class</option>

                    <?php while ($class = mysqli_fetch_assoc($classes)) { ?>
                        <option 
                            value="<?php echo $class['id']; ?>"
                            <?php if ($class['id'] == $edit_assignment['class_id']) echo "selected"; ?>
                        >
                            <?php echo $class['class_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Assignment Name</label>
                <input 
                    type="text" 
                    name="assignment_name" 
                    value="<?php echo $edit_assignment['assignment_name']; ?>" 
                    required
                >

                <label>Description</label>
                <input 
                    type="text" 
                    name="description" 
                    value="<?php echo $edit_assignment['description']; ?>"
                >

                <label>Due Date</label>
                <input 
                    type="date" 
                    name="due_date" 
                    value="<?php echo $edit_assignment['due_date']; ?>" 
                    required
                >

                <label>Points</label>
                <input 
                    type="number" 
                    name="points" 
                    value="<?php echo $edit_assignment['points']; ?>" 
                    required
                >

                <button type="submit" name="update_assignment">
                    Update Assignment
                </button>

                <a href="assignments.php">Cancel</a>

            </form>

        <?php } else { ?>

            <h3>Add New Assignment</h3>

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

                <label>Assignment Name</label>
                <input type="text" name="assignment_name" placeholder="Example: Homework 1" required>

                <label>Description</label>
                <input type="text" name="description" placeholder="Example: Complete Chapter 1 questions">

                <label>Due Date</label>
                <input type="date" name="due_date" required>

                <label>Points</label>
                <input type="number" name="points" placeholder="Example: 100" required>

                <button type="submit" name="add_assignment">
                    Add Assignment
                </button>

            </form>

        <?php } ?>

    </div>

    <div class="table-box">
        <h3>Assignment List</h3>

        <table>
            <tr>
                <th>Class</th>
                <th>Assignment</th>
                <th>Description</th>
                <th>Due Date</th>
                <th>Points</th>
                <th>Actions</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($assignments)) { ?>
                <tr>
                    <td><?php echo $row['class_name']; ?></td>
                    <td><?php echo $row['assignment_name']; ?></td>
                    <td><?php echo $row['description']; ?></td>
                    <td><?php echo $row['due_date']; ?></td>
                    <td><?php echo $row['points']; ?></td>
                    <td>
                        <a href="assignments.php?edit=<?php echo $row['id']; ?>">Edit</a>
                        |
                        <a 
                            href="assignments.php?delete=<?php echo $row['id']; ?>"
                            onclick="return confirm('Are you sure you want to delete this assignment?');"
                        >
                            Delete
                        </a>
                    </td>
                </tr>
            <?php } ?>

        </table>
    </div>
</section>

</body>
</html>