<?php
include "db.php";

/*
    update.php

    This page updates an existing album.
    The user can change the album title, artist name,
    current track details, delete tracks, and add new tracks.

    The artist field uses a datalist so existing artists appear
    as suggestions, but a new artist name can still be entered.

    In this database, IDs are not auto-generated for ArtistId and TrackId,
    so MAX(...) + 1 is used when needed.
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

// Load artists for datalist suggestions
$sqlArtists = "SELECT Name FROM artists ORDER BY Name ASC";
$resultArtists = $conn->query($sqlArtists);

$currentAlbumTitle = $album["Title"];
$currentArtistName = $album["ArtistName"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $currentAlbumTitle = isset($_POST["album_title"]) ? trim($_POST["album_title"]) : "";
    $currentArtistName = isset($_POST["artist_name"]) ? trim($_POST["artist_name"]) : "";
    $originalArtistId = isset($_POST["original_artist_id"]) ? (int)$_POST["original_artist_id"] : 0;

    $trackIds = isset($_POST["track_ids"]) ? $_POST["track_ids"] : array();
    $tracks = isset($_POST["tracks"]) ? $_POST["tracks"] : array();
    $composers = isset($_POST["composers"]) ? $_POST["composers"] : array();
    $deleteTracks = isset($_POST["delete_tracks"]) ? $_POST["delete_tracks"] : array();
    $newTracks = isset($_POST["new_tracks"]) ? $_POST["new_tracks"] : array();

    if ($currentAlbumTitle == "" || $currentArtistName == "") {
        $message = "Please enter both album and artist.";
        $messageClass = "error";
    } else {
        $remainingTrackCount = 0;

        // Count tracks that will remain after update
        for ($i = 0; $i < count($trackIds); $i++) {
            $trackId = (int)$trackIds[$i];
            $trackName = isset($tracks[$i]) ? trim($tracks[$i]) : "";

            if (!in_array((string)$trackId, $deleteTracks) && $trackName != "") {
                $remainingTrackCount++;
            }
        }

        // Count any new tracks added in the form
        foreach ($newTracks as $newTrack) {
            $newTrackName = isset($newTrack["name"]) ? trim($newTrack["name"]) : "";

            if ($newTrackName != "") {
                $remainingTrackCount++;
            }
        }

        if ($remainingTrackCount == 0) {
            $message = "Please keep or add at least one track.";
            $messageClass = "error";
        } else {
            $albumTitleSafe = $conn->real_escape_string($currentAlbumTitle);
            $artistNameSafe = $conn->real_escape_string($currentArtistName);

            // Check for another album with the same title and artist
            $sqlDuplicateAlbum = "SELECT albums.AlbumId
                                  FROM albums
                                  INNER JOIN artists ON albums.ArtistId = artists.ArtistId
                                  WHERE albums.Title = '$albumTitleSafe'
                                  AND artists.Name = '$artistNameSafe'
                                  AND albums.AlbumId != $albumId
                                  LIMIT 1";
            $resultDuplicateAlbum = $conn->query($sqlDuplicateAlbum);

            if ($resultDuplicateAlbum && $resultDuplicateAlbum->num_rows > 0) {
                $message = "Another album with the same title and artist already exists.";
                $messageClass = "error";
            }

            if ($message == "") {
                $conn->begin_transaction();

                try {
                    $newArtistId = $originalArtistId;

                    // Check if the entered artist already exists
                    $sqlArtistCheck = "SELECT ArtistId FROM artists WHERE Name = '$artistNameSafe' LIMIT 1";
                    $resultArtistCheck = $conn->query($sqlArtistCheck);

                    if (!$resultArtistCheck) {
                        throw new Exception("Error checking artist.");
                    }

                    if ($resultArtistCheck->num_rows > 0) {
                        $rowArtist = $resultArtistCheck->fetch_assoc();
                        $newArtistId = $rowArtist["ArtistId"];
                    } else {
                        // ArtistId is not auto-increment in this database,
                        // so the next ID is created manually using MAX + 1.
                        $sqlMaxArtist = "SELECT IFNULL(MAX(ArtistId), 0) AS maxArtistId FROM artists";
                        $resultMaxArtist = $conn->query($sqlMaxArtist);

                        if (!$resultMaxArtist) {
                            throw new Exception("Error finding artist ID.");
                        }

                        $rowMaxArtist = $resultMaxArtist->fetch_assoc();
                        $newArtistId = $rowMaxArtist["maxArtistId"] + 1;

                        $sqlInsertArtist = "INSERT INTO artists (ArtistId, Name)
                                            VALUES ($newArtistId, '$artistNameSafe')";

                        if (!$conn->query($sqlInsertArtist)) {
                            throw new Exception("Error inserting artist.");
                        }
                    }

                    // Update album title and artist
                    $sqlUpdateAlbum = "UPDATE albums
                                       SET Title = '$albumTitleSafe', ArtistId = $newArtistId
                                       WHERE AlbumId = $albumId";

                    if (!$conn->query($sqlUpdateAlbum)) {
                        throw new Exception("Error updating album.");
                    }

                    // Update current tracks or delete selected tracks
                    for ($i = 0; $i < count($trackIds); $i++) {
                        $trackId = (int)$trackIds[$i];
                        $trackName = isset($tracks[$i]) ? trim($tracks[$i]) : "";
                        $composerName = isset($composers[$i]) ? trim($composers[$i]) : "";

                        if (in_array((string)$trackId, $deleteTracks)) {
                            $sqlDeleteTrack = "DELETE FROM tracks WHERE TrackId = $trackId";

                            if (!$conn->query($sqlDeleteTrack)) {
                                throw new Exception("Error deleting track.");
                            }
                        } else {
                            if ($trackName != "") {
                                $trackNameSafe = $conn->real_escape_string($trackName);
                                $composerSafe = $conn->real_escape_string($composerName);

                                if ($composerSafe == "") {
                                    $composerValue = "NULL";
                                } else {
                                    $composerValue = "'$composerSafe'";
                                }

                                $sqlUpdateTrack = "UPDATE tracks
                                                   SET Name = '$trackNameSafe', Composer = $composerValue
                                                   WHERE TrackId = $trackId";

                                if (!$conn->query($sqlUpdateTrack)) {
                                    throw new Exception("Error updating track.");
                                }
                            }
                        }
                    }

                    // TrackId is not auto-increment in this database,
                    // so the next ID is created manually using MAX + 1.
                    $sqlMaxTrack = "SELECT IFNULL(MAX(TrackId), 0) AS maxTrackId FROM tracks";
                    $resultMaxTrack = $conn->query($sqlMaxTrack);

                    if (!$resultMaxTrack) {
                        throw new Exception("Error finding track ID.");
                    }

                    $rowMaxTrack = $resultMaxTrack->fetch_assoc();
                    $nextTrackId = $rowMaxTrack["maxTrackId"] + 1;

                    foreach ($newTracks as $newTrack) {
                        $newTrackName = isset($newTrack["name"]) ? trim($newTrack["name"]) : "";
                        $newComposerName = isset($newTrack["composer"]) ? trim($newTrack["composer"]) : "";

                        if ($newTrackName != "") {
                            $newTrackSafe = $conn->real_escape_string($newTrackName);
                            $newComposerSafe = $conn->real_escape_string($newComposerName);

                            if ($newComposerSafe == "") {
                                $newComposerValue = "NULL";
                            } else {
                                $newComposerValue = "'$newComposerSafe'";
                            }

                            $sqlInsertTrack = "INSERT INTO tracks
                                               (TrackId, Name, AlbumId, MediaTypeId, Composer, Milliseconds, UnitPrice)
                                               VALUES
                                               ($nextTrackId, '$newTrackSafe', $albumId, 1, $newComposerValue, 200000, 0.99)";

                            if (!$conn->query($sqlInsertTrack)) {
                                throw new Exception("Error inserting new track.");
                            }

                            $nextTrackId++;
                        }
                    }

                    // Delete the old artist only if no albums are linked to it
                    if ($newArtistId != $originalArtistId) {
                        $sqlCheckOldArtist = "SELECT COUNT(*) AS total FROM albums WHERE ArtistId = $originalArtistId";
                        $resultCheckOldArtist = $conn->query($sqlCheckOldArtist);

                        if (!$resultCheckOldArtist) {
                            throw new Exception("Error checking old artist.");
                        }

                        $rowOldArtist = $resultCheckOldArtist->fetch_assoc();

                        if ($rowOldArtist["total"] == 0) {
                            $sqlDeleteOldArtist = "DELETE FROM artists WHERE ArtistId = $originalArtistId";

                            if (!$conn->query($sqlDeleteOldArtist)) {
                                throw new Exception("Error deleting old artist.");
                            }
                        }
                    }

                    $conn->commit();
                    header("Location: details.php?id=" . $albumId . "&updated=1&album=" . urlencode($currentAlbumTitle));
                    exit();

                } catch (Exception $e) {
                    $conn->rollback();
                    $message = $e->getMessage();
                    $messageClass = "error";
                }
            }
        }
    }
}

// Load current tracks
$sqlTracks = "SELECT TrackId, Name, Composer
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
    <title>Update Album</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function addNewTrackField() {
            const container = document.getElementById("newTrackContainer");
            const count = container.children.length + 1;

            const div = document.createElement("div");
            div.className = "track-box";
            div.innerHTML = `
                <h3>New Track ${count}</h3>
                <label>Track Name</label>
                <input type="text" name="new_tracks[${count - 1}][name]">

                <label>Composer</label>
                <input type="text" name="new_tracks[${count - 1}][composer]" placeholder="Optional">
            `;

            container.appendChild(div);
        }

        function removeNewTrackField() {
            const container = document.getElementById("newTrackContainer");

            if (container.children.length > 0) {
                container.removeChild(container.lastElementChild);
            }
        }
    </script>
</head>
<body>

<div class="background-overlay"></div>

<div class="container">
    <div class="card">
        <a href="index.php#album-list" class="btn btn-secondary">Back</a>
        <br><br>

        <h2>Update Album</h2>
        <p class="sub-text">Update album details, artist information, and related tracks.</p>

        <?php
        if ($message != "") {
            echo "<div class='message $messageClass'>" . htmlspecialchars($message) . "</div>";
        }
        ?>

        <form method="post">
            <input type="hidden" name="original_artist_id" value="<?php echo $album["ArtistId"]; ?>">

            <div class="form-grid">
                <div>
                    <label for="album_title">Album</label>
                    <input type="text" name="album_title" id="album_title" value="<?php echo htmlspecialchars($currentAlbumTitle); ?>" required>
                </div>

                <div>
                    <label for="artist_name">Artist</label>
                    <input
                        type="text"
                        name="artist_name"
                        id="artist_name"
                        list="artist_list"
                        value="<?php echo htmlspecialchars($currentArtistName); ?>"
                        placeholder="Type artist name"
                        autocomplete="off"
                        required
                    >
                    <datalist id="artist_list">
                        <?php
                        if ($resultArtists && $resultArtists->num_rows > 0) {
                            while ($artist = $resultArtists->fetch_assoc()) {
                                echo "<option value=\"" . htmlspecialchars($artist["Name"], ENT_QUOTES) . "\"></option>";
                            }
                        }
                        ?>
                    </datalist>
                </div>
            </div>

            <h2>Current Tracks</h2>

            <?php
            if ($resultTracks && $resultTracks->num_rows > 0) {
                $count = 1;

                while ($track = $resultTracks->fetch_assoc()) {
                    $composer = $track["Composer"];
                    if ($composer == null) {
                        $composer = "";
                    }

                    echo "<div class='track-box'>";
                    echo "<h3>Track " . $count . "</h3>";
                    echo "<input type=\"hidden\" name=\"track_ids[]\" value=\"" . $track["TrackId"] . "\">";

                    echo "<label>Track Name</label>";
                    echo "<input type=\"text\" name=\"tracks[]\" value=\"" . htmlspecialchars($track["Name"], ENT_QUOTES) . "\" required>";

                    echo "<label>Composer</label>";
                    echo "<input type=\"text\" name=\"composers[]\" value=\"" . htmlspecialchars($composer, ENT_QUOTES) . "\" placeholder=\"Optional\">";

                    echo "<label class=\"delete-label\">";
                    echo "<input type=\"checkbox\" name=\"delete_tracks[]\" value=\"" . $track["TrackId"] . "\"> Delete this track";
                    echo "</label>";
                    echo "</div>";

                    $count++;
                }
            } else {
                echo "<p>No current tracks found for this album.</p>";
            }
            ?>

            <h2>Add New Tracks</h2>
            <div id="newTrackContainer"></div>

            <div class="button-row">
                <button type="button" class="btn btn-success" onclick="addNewTrackField()">Add Track</button>
                <button type="button" class="btn btn-secondary" onclick="removeNewTrackField()">Remove Track</button>
            </div>

            <br>
            <button type="submit" class="btn">Submit Update</button>
        </form>
    </div>
</div>

</body>
</html>
