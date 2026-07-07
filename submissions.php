<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

$message = "";

/* ADD SUBMISSION */
if (isset($_POST['add_submission'])) {
    $student_id = $_POST['student_id'];
    $assignment_id = $_POST['assignment_id'];
    $submission_text = trim($_POST['submission_text']);

    if (!empty($student_id) && !empty($assignment_id) && !empty($submission_text)) {
        $sql = "INSERT INTO submissions (student_id, assignment_id, submission_text)
                VALUES ('$student_id', '$assignment_id', '$submission_text')";

        if (mysqli_query($conn, $sql)) {
            $message = "Submission added successfully.";
        } else {
            $message = "Error adding submission.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* DELETE SUBMISSION */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $sql = "DELETE FROM submissions WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $message = "Submission deleted successfully.";
    } else {
        $message = "Error deleting submission.";
    }
}

/* GET SUBMISSION FOR EDIT */
$edit_submission = null;

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];

    $edit_result = mysqli_query($conn, "SELECT * FROM submissions WHERE id = $id");
    $edit_submission = mysqli_fetch_assoc($edit_result);
}

/* UPDATE SUBMISSION */
if (isset($_POST['update_submission'])) {
    $id = $_POST['id'];
    $student_id = $_POST['student_id'];
    $assignment_id = $_POST['assignment_id'];
    $submission_text = trim($_POST['submission_text']);

    if (!empty($student_id) && !empty($assignment_id) && !empty($submission_text)) {
        $sql = "UPDATE submissions
                SET student_id = '$student_id',
                    assignment_id = '$assignment_id',
                    submission_text = '$submission_text'
                WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            $message = "Submission updated successfully.";
            $edit_submission = null;
        } else {
            $message = "Error updating submission.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* GET STUDENTS */
$students = mysqli_query($conn, "SELECT * FROM students");

/* GET ASSIGNMENTS */
$assignments = mysqli_query($conn, "SELECT * FROM assignments");

/* GET SUBMISSIONS */
$submissions = mysqli_query($conn, "
    SELECT submissions.id,
           submissions.student_id,
           submissions.assignment_id,
           submissions.submission_text,
           submissions.submitted_at,
           submissions.status,
           students.first_name,
           students.last_name,
           assignments.assignment_name
    FROM submissions
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
    <title>Manage Submissions</title>
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
    <h2>Manage Submissions</h2>
    <p>Students can submit assignment work for review and feedback.</p>

    <?php if (!empty($message)) { ?>
        <p><strong><?php echo $message; ?></strong></p>
    <?php } ?>

    <div class="form-box">

        <?php if ($edit_submission) { ?>

            <h3>Edit Submission</h3>

            <form method="POST">

                <input type="hidden" name="id" value="<?php echo $edit_submission['id']; ?>">

                <label>Student</label>
                <select name="student_id" required>
                    <option value="">Select Student</option>

                    <?php while ($student = mysqli_fetch_assoc($students)) { ?>
                        <option 
                            value="<?php echo $student['student_id']; ?>"
                            <?php if ($student['student_id'] == $edit_submission['student_id']) echo "selected"; ?>
                        >
                            <?php echo $student['first_name'] . " " . $student['last_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Assignment</label>
                <select name="assignment_id" required>
                    <option value="">Select Assignment</option>

                    <?php while ($assignment = mysqli_fetch_assoc($assignments)) { ?>
                        <option 
                            value="<?php echo $assignment['id']; ?>"
                            <?php if ($assignment['id'] == $edit_submission['assignment_id']) echo "selected"; ?>
                        >
                            <?php echo $assignment['assignment_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Submission Text</label>
                <textarea name="submission_text" required><?php echo $edit_submission['submission_text']; ?></textarea>

                <button type="submit" name="update_submission">
                    Update Submission
                </button>

                <a href="submissions.php">Cancel</a>

            </form>

        <?php } else { ?>

            <h3>Add New Submission</h3>

            <form method="POST">

                <label>Student</label>
                <select name="student_id" required>
                    <option value="">Select Student</option>

                    <?php while ($student = mysqli_fetch_assoc($students)) { ?>
                        <option value="<?php echo $student['student_id']; ?>">
                            <?php echo $student['first_name'] . " " . $student['last_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Assignment</label>
                <select name="assignment_id" required>
                    <option value="">Select Assignment</option>

                    <?php while ($assignment = mysqli_fetch_assoc($assignments)) { ?>
                        <option value="<?php echo $assignment['id']; ?>">
                            <?php echo $assignment['assignment_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Submission Text</label>
                <textarea name="submission_text" placeholder="Paste the student assignment submission here..." required></textarea>

                <button type="submit" name="add_submission">
                    Submit
                </button>

            </form>

        <?php } ?>

    </div>

    <div class="table-box">
        <h3>Submission List</h3>

        <table>
            <tr>
                <th>Student</th>
                <th>Assignment</th>
                <th>Submission</th>
                <th>Submitted At</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($submissions)) { ?>
                <tr>
                    <td><?php echo $row['first_name'] . " " . $row['last_name']; ?></td>
                    <td><?php echo $row['assignment_name']; ?></td>
                    <td><?php echo $row['submission_text']; ?></td>
                    <td><?php echo $row['submitted_at']; ?></td>
                    <td><?php echo $row['status']; ?></td>
                    <td>
                        <a href="submissions.php?edit=<?php echo $row['id']; ?>">Edit</a>
                        |
                        <a 
                            href="submissions.php?delete=<?php echo $row['id']; ?>"
                            onclick="return confirm('Are you sure you want to delete this submission?');"
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