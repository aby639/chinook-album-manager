<?php
include "db.php";

/*
    delete.php

    This page deletes a selected album from the Chinook database.
    It shows the related album, artist and all tracks first.

    All tracks linked to this album will be deleted.
    The artist will only be deleted if no other albums are linked
    to that artist after the album is removed.
*/

$message = "";
$messageClass = "";

if (!isset($_GET["id"])) {
    echo "No album selected.";
    exit();
}

$albumId = (int)$_GET["id"];

// Load album and artist details
$sqlAlbum = "SELECT albums.AlbumId, albums.Title, artists.ArtistId, artists.Name AS ArtistName
             FROM albums
             INNER JOIN artists ON albums.ArtistId = artists.ArtistId
             WHERE albums.AlbumId = $albumId";
$resultAlbum = $conn->query($sqlAlbum);

if (!$resultAlbum || $resultAlbum->num_rows == 0) {
    echo "Album not found.";
    exit();
}

$album = $resultAlbum->fetch_assoc();
$artistId = $album["ArtistId"];

// Count other albums by the same artist
$sqlOtherAlbums = "SELECT COUNT(*) AS total
                   FROM albums
                   WHERE ArtistId = $artistId
                   AND AlbumId != $albumId";
$resultOtherAlbums = $conn->query($sqlOtherAlbums);

$otherAlbumCount = 0;
if ($resultOtherAlbums) {
    $rowOtherAlbums = $resultOtherAlbums->fetch_assoc();
    $otherAlbumCount = $rowOtherAlbums["total"];
}

// Start delete only after the user confirms
if (isset($_POST["confirm_delete"])) {

    $deletedAlbumTitle = $album["Title"];

    $conn->begin_transaction();

    try {
        // Delete the tracks first because they are linked to the album
        $sqlDeleteTracks = "DELETE FROM tracks WHERE AlbumId = $albumId";
        if (!$conn->query($sqlDeleteTracks)) {
            throw new Exception("Error deleting tracks.");
        }

        // Delete the selected album
        $sqlDeleteAlbum = "DELETE FROM albums WHERE AlbumId = $albumId";
        if (!$conn->query($sqlDeleteAlbum)) {
            throw new Exception("Error deleting album.");
        }

        // Check whether the artist still has any albums left
        $sqlCheckArtistAlbums = "SELECT COUNT(*) AS total FROM albums WHERE ArtistId = $artistId";
        $resultCheckArtistAlbums = $conn->query($sqlCheckArtistAlbums);

        if (!$resultCheckArtistAlbums) {
            throw new Exception("Error checking artist records.");
        }

        $rowArtistAlbums = $resultCheckArtistAlbums->fetch_assoc();

        // Delete the artist only if this was the last album
        if ($rowArtistAlbums["total"] == 0) {
            $sqlDeleteArtist = "DELETE FROM artists WHERE ArtistId = $artistId";

            if (!$conn->query($sqlDeleteArtist)) {
                throw new Exception("Error deleting artist.");
            }
        }

        $conn->commit();
        header("Location: index.php?deleted=1&album=" . urlencode($deletedAlbumTitle) . "#album-list");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
        $messageClass = "error";
    }
}

// Load tracks so the user can see what will be removed
$sqlTracks = "SELECT TrackId, Name
              FROM tracks
              WHERE AlbumId = $albumId
              ORDER BY TrackId ASC";
$resultTracks = $conn->query($sqlTracks);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Album</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="background-overlay"></div>

<div class="container">
    <div class="card">
        <a href="index.php#album-list" class="btn btn-secondary">Back</a>
        <br><br>

        <h2>Delete Album</h2>

        <?php
        if ($message != "") {
            echo "<div class='message $messageClass'>" . htmlspecialchars($message) . "</div>";
        } else {
            echo "<div class='message error'>Are you sure you want to delete this album and its related information?</div>";
        }
        ?>

        <div class="info-box danger-box">
            <p><strong>Album ID:</strong> <?php echo $album["AlbumId"]; ?></p>
            <p><strong>Album:</strong> <?php echo htmlspecialchars($album["Title"]); ?></p>
            <p><strong>Artist ID:</strong> <?php echo $album["ArtistId"]; ?></p>
            <p><strong>Artist:</strong> <?php echo htmlspecialchars($album["ArtistName"]); ?></p>
        </div>

        <div class="message error" style="margin-top: 12px;">
            All tracks linked to this album will be deleted.
            <?php if ($otherAlbumCount > 0) { ?>
                This artist has other album(s), so the artist record will stay.
            <?php } else { ?>
                This is the artist's last album, so the artist record will also be deleted.
            <?php } ?>
        </div>

        <h2>Tracks That Will Be Deleted</h2>

        <?php
        if ($resultTracks && $resultTracks->num_rows > 0) {
            echo "<ul class='track-list'>";

            while ($track = $resultTracks->fetch_assoc()) {
                echo "<li><strong>Track ID:</strong> " . $track["TrackId"] . " - " . htmlspecialchars($track["Name"]) . "</li>";
            }

            echo "</ul>";
        } else {
            echo "<p>No tracks found for this album.</p>";
        }
        ?>

        <form method="post" onsubmit="return confirm('Are you sure you want to delete this album?');">
            <button type="submit" name="confirm_delete" class="btn btn-danger">Yes, Delete</button>
            <a href="index.php#album-list" class="btn btn-secondary">No, Cancel</a>
        </form>
    </div>
</div>

</body>
</html>
