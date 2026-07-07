<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

$message = "";

/* ADD GRADE */
if (isset($_POST['add_grade'])) {
    $submission_id = $_POST['submission_id'];
    $points_earned = $_POST['points_earned'];
    $feedback = trim($_POST['feedback']);

    if (!empty($submission_id) && !empty($points_earned) && !empty($feedback)) {
        $sql = "INSERT INTO grades (submission_id, points_earned, feedback)
                VALUES ('$submission_id', '$points_earned', '$feedback')";

        if (mysqli_query($conn, $sql)) {
            $message = "Grade and feedback added successfully.";
        } else {
            $message = "Error adding grade.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* DELETE GRADE */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $sql = "DELETE FROM grades WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $message = "Grade deleted successfully.";
    } else {
        $message = "Error deleting grade.";
    }
}

/* GET GRADE FOR EDIT */
$edit_grade = null;

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];

    $edit_result = mysqli_query($conn, "SELECT * FROM grades WHERE id = $id");
    $edit_grade = mysqli_fetch_assoc($edit_result);
}

/* UPDATE GRADE */
if (isset($_POST['update_grade'])) {
    $id = $_POST['id'];
    $submission_id = $_POST['submission_id'];
    $points_earned = $_POST['points_earned'];
    $feedback = trim($_POST['feedback']);

    if (!empty($submission_id) && !empty($points_earned) && !empty($feedback)) {
        $sql = "UPDATE grades
                SET submission_id = '$submission_id',
                    points_earned = '$points_earned',
                    feedback = '$feedback'
                WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            $message = "Grade updated successfully.";
            $edit_grade = null;
        } else {
            $message = "Error updating grade.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* GET SUBMISSIONS */
$submissions = mysqli_query($conn, "
    SELECT submissions.id,
           submissions.submission_text,
           students.first_name,
           students.last_name,
           assignments.assignment_name
    FROM submissions
    LEFT JOIN students
    ON submissions.student_id = students.student_id
    LEFT JOIN assignments
    ON submissions.assignment_id = assignments.id
");

/* GET GRADES */
$grades = mysqli_query($conn, "
    SELECT grades.id,
           grades.submission_id,
           grades.points_earned,
           grades.feedback,
           grades.graded_at,
           submissions.submission_text,
           students.first_name,
           students.last_name,
           assignments.assignment_name
    FROM grades
    LEFT JOIN submissions
    ON grades.submission_id = submissions.id
    LEFT JOIN students
    ON submissions.student_id = students.student_id
    LEFT JOIN assignments
    ON submissions.assignment_id = assignments.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades</title>
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
    <h2>Manage Grades</h2>
    <p>Create, view, edit, and delete grades and feedback for student submissions.</p>

    <?php if (!empty($message)) { ?>
        <p><strong><?php echo $message; ?></strong></p>
    <?php } ?>

    <div class="form-box">

        <?php if ($edit_grade) { ?>

            <h3>Edit Grade and Feedback</h3>

            <form method="POST">

                <input type="hidden" name="id" value="<?php echo $edit_grade['id']; ?>">

                <label>Submission</label>
                <select name="submission_id" required>
                    <option value="">Select Submission</option>

                    <?php while ($submission = mysqli_fetch_assoc($submissions)) { ?>
                        <option 
                            value="<?php echo $submission['id']; ?>"
                            <?php if ($submission['id'] == $edit_grade['submission_id']) echo "selected"; ?>
                        >
                            <?php echo $submission['first_name'] . " " . $submission['last_name'] . " - " . $submission['assignment_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Points Earned</label>
                <input 
                    type="number" 
                    name="points_earned" 
                    value="<?php echo $edit_grade['points_earned']; ?>" 
                    required
                >

                <label>Feedback</label>
                <textarea 
                    name="feedback" 
                    rows="5" 
                    required
                ><?php echo $edit_grade['feedback']; ?></textarea>

                <button type="submit" name="update_grade">
                    Update Grade
                </button>

                <a href="grades.php">Cancel</a>

            </form>

        <?php } else { ?>

            <h3>Add New Grade and Feedback</h3>

            <form method="POST">

                <label>Submission</label>
                <select name="submission_id" required>
                    <option value="">Select Submission</option>

                    <?php while ($submission = mysqli_fetch_assoc($submissions)) { ?>
                        <option value="<?php echo $submission['id']; ?>">
                            <?php echo $submission['first_name'] . " " . $submission['last_name'] . " - " . $submission['assignment_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Points Earned</label>
                <input type="number" name="points_earned" placeholder="Example: 90" required>

                <label>Feedback</label>
                <textarea name="feedback" rows="5" placeholder="Example: Good work, but add more comments." required></textarea>

                <button type="submit" name="add_grade">
                    Add Grade
                </button>

            </form>

        <?php } ?>

    </div>

    <div class="table-box">
        <h3>Grade List</h3>

        <table>
            <tr>
                <th>Student</th>
                <th>Assignment</th>
                <th>Points Earned</th>
                <th>Feedback</th>
                <th>Graded At</th>
                <th>Actions</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($grades)) { ?>
                <tr>
                    <td><?php echo $row['first_name'] . " " . $row['last_name']; ?></td>
                    <td><?php echo $row['assignment_name']; ?></td>
                    <td><?php echo $row['points_earned']; ?></td>
                    <td><?php echo $row['feedback']; ?></td>
                    <td><?php echo $row['graded_at']; ?></td>
                    <td>
                        <a href="grades.php?edit=<?php echo $row['id']; ?>">Edit</a>
                        |
                        <a 
                            href="grades.php?delete=<?php echo $row['id']; ?>"
                            onclick="return confirm('Are you sure you want to delete this grade?');"
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