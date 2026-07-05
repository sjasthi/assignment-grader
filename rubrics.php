<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

$message = "";

/* ADD RUBRIC */
if (isset($_POST['add_rubric'])) {
    $assignment_id = $_POST['assignment_id'];
    $criterion = trim($_POST['criterion']);
    $points = $_POST['points'];

    if (!empty($assignment_id) && !empty($criterion) && !empty($points)) {
        $sql = "INSERT INTO rubrics (assignment_id, criterion, points)
                VALUES ('$assignment_id', '$criterion', '$points')";

        if (mysqli_query($conn, $sql)) {
            $message = "Rubric added successfully.";
        } else {
            $message = "Error adding rubric.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* DELETE RUBRIC */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $sql = "DELETE FROM rubrics WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $message = "Rubric deleted successfully.";
    } else {
        $message = "Error deleting rubric.";
    }
}

/* GET RUBRIC FOR EDIT */
$edit_rubric = null;

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];

    $edit_result = mysqli_query($conn, "SELECT * FROM rubrics WHERE id = $id");
    $edit_rubric = mysqli_fetch_assoc($edit_result);
}

/* UPDATE RUBRIC */
if (isset($_POST['update_rubric'])) {
    $id = $_POST['id'];
    $assignment_id = $_POST['assignment_id'];
    $criterion = trim($_POST['criterion']);
    $points = $_POST['points'];

    if (!empty($assignment_id) && !empty($criterion) && !empty($points)) {
        $sql = "UPDATE rubrics
                SET assignment_id = '$assignment_id',
                    criterion = '$criterion',
                    points = '$points'
                WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            $message = "Rubric updated successfully.";
            $edit_rubric = null;
        } else {
            $message = "Error updating rubric.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* GET ASSIGNMENTS */
$assignments = mysqli_query($conn, "SELECT * FROM assignments");

/* GET RUBRICS */
$rubrics = mysqli_query($conn, "
    SELECT rubrics.id,
           rubrics.assignment_id,
           rubrics.criterion,
           rubrics.points,
           assignments.assignment_name
    FROM rubrics
    LEFT JOIN assignments
    ON rubrics.assignment_id = assignments.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rubrics</title>
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
        </nav>

        <a href="#" class="cs-header-button">Login</a>

    </div>
</header>

<section class="page-section">
    <h2>Manage Rubrics</h2>
    <p>Create, view, edit, and delete rubric criteria for each assignment.</p>

    <?php if (!empty($message)) { ?>
        <p><strong><?php echo $message; ?></strong></p>
    <?php } ?>

    <div class="form-box">

        <?php if ($edit_rubric) { ?>

            <h3>Edit Rubric Criterion</h3>

            <form method="POST">

                <input type="hidden" name="id" value="<?php echo $edit_rubric['id']; ?>">

                <label>Assignment</label>
                <select name="assignment_id" required>
                    <option value="">Select Assignment</option>

                    <?php while ($assignment = mysqli_fetch_assoc($assignments)) { ?>
                        <option 
                            value="<?php echo $assignment['id']; ?>"
                            <?php if ($assignment['id'] == $edit_rubric['assignment_id']) echo "selected"; ?>
                        >
                            <?php echo $assignment['assignment_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Criterion</label>
                <input 
                    type="text" 
                    name="criterion" 
                    value="<?php echo $edit_rubric['criterion']; ?>" 
                    required
                >

                <label>Points</label>
                <input 
                    type="number" 
                    name="points" 
                    value="<?php echo $edit_rubric['points']; ?>" 
                    required
                >

                <button type="submit" name="update_rubric">
                    Update Rubric
                </button>

                <a href="rubrics.php">Cancel</a>

            </form>

        <?php } else { ?>

            <h3>Add New Rubric Criterion</h3>

            <form method="POST">

                <label>Assignment</label>
                <select name="assignment_id" required>
                    <option value="">Select Assignment</option>

                    <?php while ($assignment = mysqli_fetch_assoc($assignments)) { ?>
                        <option value="<?php echo $assignment['id']; ?>">
                            <?php echo $assignment['assignment_name']; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Criterion</label>
                <input type="text" name="criterion" placeholder="Example: Correctness" required>

                <label>Points</label>
                <input type="number" name="points" placeholder="Example: 50" required>

                <button type="submit" name="add_rubric">
                    Add Rubric
                </button>

            </form>

        <?php } ?>

    </div>

    <div class="table-box">
        <h3>Rubric List</h3>

        <table>
            <tr>
                <th>Assignment</th>
                <th>Criterion</th>
                <th>Points</th>
                <th>Actions</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($rubrics)) { ?>
                <tr>
                    <td><?php echo $row['assignment_name']; ?></td>
                    <td><?php echo $row['criterion']; ?></td>
                    <td><?php echo $row['points']; ?></td>
                    <td>
                        <a href="rubrics.php?edit=<?php echo $row['id']; ?>">Edit</a>
                        |
                        <a 
                            href="rubrics.php?delete=<?php echo $row['id']; ?>"
                            onclick="return confirm('Are you sure you want to delete this rubric?');"
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