<?php
include "db.php";

/*
    details.php

    This page shows the full details of one selected album.
    It displays album details, artist details, and the tracks linked to that album.
*/

if (!isset($_GET["id"])) {
    echo "No album selected.";
    exit();
}

$albumId = (int)$_GET["id"];
// Load the selected album together with its artist details
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
// Load all tracks that belong to this album
$sqlTracks = "SELECT TrackId, Name, Composer
              FROM tracks
              WHERE AlbumId = $albumId
              ORDER BY TrackId ASC";
$resultTracks = $conn->query($sqlTracks);
// Count the tracks so the total can be shown in the info box
$trackCount = 0;
if ($resultTracks) {
    $trackCount = $resultTracks->num_rows;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Album Details</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="background-overlay"></div>

<div class="container">
    <div class="card">
        <a href="index.php#album-list" class="btn btn-secondary">Back</a>
        <br><br>

        <?php if (isset($_GET["updated"])) { ?>
            <div class="message success auto-hide">
                <?php
                if (isset($_GET["album"]) && trim($_GET["album"]) != "") {
                    echo "Album \"" . htmlspecialchars($_GET["album"]) . "\" updated successfully.";
                } else {
                    echo "Album updated successfully.";
                }
                ?>
            </div>
        <?php } ?>

        <h2>Album Details</h2>

        <div class="info-box">
            <p><strong>Album ID:</strong> <?php echo $album["AlbumId"]; ?></p>
            <p><strong>Album:</strong> <?php echo htmlspecialchars($album["Title"]); ?></p>
            <p><strong>Artist ID:</strong> <?php echo $album["ArtistId"]; ?></p>
            <p><strong>Artist:</strong> <?php echo htmlspecialchars($album["ArtistName"]); ?></p>
            <p><strong>Total Tracks:</strong> <?php echo $trackCount; ?></p>
        </div>

        <h2>Tracks in this Album</h2>

        <?php if ($resultTracks && $resultTracks->num_rows > 0) { ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="nowrap-col">Track ID</th>
                            <th>Track Name</th>
                            <th>Composer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($track = $resultTracks->fetch_assoc()) { ?>
                            <?php
                            $composer = $track["Composer"];
                            if ($composer == "" || $composer == null) {
                                $composer = "N/A";
                            }
                            ?>
                            <tr>
                                <td class="nowrap-col"><?php echo $track["TrackId"]; ?></td>
                                <td><?php echo htmlspecialchars($track["Name"]); ?></td>
                                <td><?php echo htmlspecialchars($composer); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p>No tracks found for this album.</p>
        <?php } ?>
    </div>
</div>

<script>
    const autoHideMessages = document.querySelectorAll(".auto-hide");

    autoHideMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.opacity = "0";
            message.style.transform = "translateY(-6px)";

            setTimeout(function() {
                message.style.display = "none";
            }, 400);
        }, 3000);
    });
</script>

</body>
</html>