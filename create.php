<?php
include "db.php";

/*
    create.php

    This page adds a new album to the Chinook database.
    The user enters a new album title and an artist name.

    The artist field uses a datalist so existing artists appear
    as suggestions, but a new artist name can still be entered.

    In this database, IDs are not auto-generated for ArtistId,
    AlbumId and TrackId, so MAX(...) + 1 is used to get the next ID.
*/

$message = "";
$messageClass = "";

$albumTitle = "";
$artistName = "";
$tracks = array(
    array("name" => "", "composer" => "")
);

// Load artist names for datalist suggestions
$sqlArtists = "SELECT Name FROM artists ORDER BY Name ASC";
$resultArtists = $conn->query($sqlArtists);

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $albumTitle = trim($_POST["album_title"]);
    $artistName = trim($_POST["artist_name"]);

    if (isset($_POST["tracks"])) {
        $tracks = $_POST["tracks"];
    }

    if ($albumTitle == "" || $artistName == "") {
        $message = "Please enter both album and artist.";
        $messageClass = "error";
    } else {

        // Only keep tracks that have a track name
        $validTracks = array();

        foreach ($tracks as $track) {
            $trackName = trim($track["name"]);
            $composerName = trim($track["composer"]);

            if ($trackName != "") {
                $validTracks[] = array(
                    "name" => $trackName,
                    "composer" => $composerName
                );
            }
        }

        if (count($validTracks) == 0) {
            $message = "Please enter at least one track.";
            $messageClass = "error";
        } else {

            // Check for duplicate track names in the same form
            $trackNamesLower = array();
            $hasDuplicateTrack = false;

            foreach ($validTracks as $track) {
                $trackNameLower = strtolower(trim($track["name"]));

                if (in_array($trackNameLower, $trackNamesLower)) {
                    $hasDuplicateTrack = true;
                    break;
                }

                $trackNamesLower[] = $trackNameLower;
            }

            if ($hasDuplicateTrack) {
                $message = "Please do not enter duplicate track names for the same album.";
                $messageClass = "error";
            }
        }

        if ($message == "") {

            $albumTitleSafe = $conn->real_escape_string($albumTitle);
            $artistNameSafe = $conn->real_escape_string($artistName);

            $artistId = 0;
            $albumId = 0;

            // Check if the same album already exists for the same artist
            $sqlDuplicateAlbum = "SELECT albums.AlbumId
                                  FROM albums
                                  INNER JOIN artists ON albums.ArtistId = artists.ArtistId
                                  WHERE albums.Title = '$albumTitleSafe'
                                  AND artists.Name = '$artistNameSafe'
                                  LIMIT 1";
            $resultDuplicateAlbum = $conn->query($sqlDuplicateAlbum);

            if ($resultDuplicateAlbum && $resultDuplicateAlbum->num_rows > 0) {
                $message = "This album already exists for this artist.";
                $messageClass = "error";
            }

            if ($message == "") {

                // Use a transaction so all related inserts are saved together
                $conn->begin_transaction();

                try {
                    // Check if the entered artist already exists
                    $sqlArtistCheck = "SELECT ArtistId FROM artists WHERE Name = '$artistNameSafe' LIMIT 1";
                    $resultArtistCheck = $conn->query($sqlArtistCheck);

                    if (!$resultArtistCheck) {
                        throw new Exception("Error checking artist.");
                    }

                    if ($resultArtistCheck->num_rows > 0) {
                        $rowArtist = $resultArtistCheck->fetch_assoc();
                        $artistId = $rowArtist["ArtistId"];
                    } else {
                        $sqlMaxArtist = "SELECT IFNULL(MAX(ArtistId), 0) AS maxArtistId FROM artists";
                        $resultMaxArtist = $conn->query($sqlMaxArtist);

                        if (!$resultMaxArtist) {
                            throw new Exception("Error finding artist ID.");
                        }

                        $rowMaxArtist = $resultMaxArtist->fetch_assoc();
                        $artistId = $rowMaxArtist["maxArtistId"] + 1;

                        $sqlInsertArtist = "INSERT INTO artists (ArtistId, Name)
                                            VALUES ($artistId, '$artistNameSafe')";

                        if (!$conn->query($sqlInsertArtist)) {
                            throw new Exception("Error inserting artist.");
                        }
                    }

                    $sqlMaxAlbum = "SELECT IFNULL(MAX(AlbumId), 0) AS maxAlbumId FROM albums";
                    $resultMaxAlbum = $conn->query($sqlMaxAlbum);

                    if (!$resultMaxAlbum) {
                        throw new Exception("Error finding album ID.");
                    }

                    $rowMaxAlbum = $resultMaxAlbum->fetch_assoc();
                    $albumId = $rowMaxAlbum["maxAlbumId"] + 1;

                    $sqlInsertAlbum = "INSERT INTO albums (AlbumId, Title, ArtistId)
                                       VALUES ($albumId, '$albumTitleSafe', $artistId)";

                    if (!$conn->query($sqlInsertAlbum)) {
                        throw new Exception("Error inserting album.");
                    }

                    $sqlMaxTrack = "SELECT IFNULL(MAX(TrackId), 0) AS maxTrackId FROM tracks";
                    $resultMaxTrack = $conn->query($sqlMaxTrack);

                    if (!$resultMaxTrack) {
                        throw new Exception("Error finding track ID.");
                    }

                    $rowMaxTrack = $resultMaxTrack->fetch_assoc();
                    $nextTrackId = $rowMaxTrack["maxTrackId"] + 1;

                    foreach ($validTracks as $track) {
                        $trackSafe = $conn->real_escape_string($track["name"]);
                        $composerSafe = $conn->real_escape_string($track["composer"]);

                        if ($composerSafe == "") {
                            $composerValue = "NULL";
                        } else {
                            $composerValue = "'$composerSafe'";
                        }

                        $sqlInsertTrack = "INSERT INTO tracks
                                           (TrackId, Name, AlbumId, MediaTypeId, Composer, Milliseconds, UnitPrice)
                                           VALUES
                                           ($nextTrackId, '$trackSafe', $albumId, 1, $composerValue, 200000, 0.99)";

                        if (!$conn->query($sqlInsertTrack)) {
                            throw new Exception("Error inserting track.");
                        }

                        $nextTrackId++;
                    }

                    $conn->commit();
                    header("Location: index.php?created=1&album=" . urlencode($albumTitle) . "#album-list");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert New Album</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function addTrackField() {
            const trackContainer = document.getElementById("trackContainer");
            const trackCount = trackContainer.children.length + 1;

            const div = document.createElement("div");
            div.className = "track-box";
            div.innerHTML = `
                <h3>Track ${trackCount}</h3>
                <label>Track Name</label>
                <input type="text" name="tracks[${trackCount - 1}][name]" placeholder="Enter track name">

                <label>Composer</label>
                <input type="text" name="tracks[${trackCount - 1}][composer]" placeholder="Optional">
            `;

            trackContainer.appendChild(div);
        }

        function removeTrackField() {
            const trackContainer = document.getElementById("trackContainer");

            if (trackContainer.children.length > 1) {
                trackContainer.removeChild(trackContainer.lastElementChild);
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

        <h2>Insert New Album</h2>
        <p class="sub-text">Enter the album title and choose or type an artist name.</p>

        <?php
        if ($message != "") {
            echo "<div class='message $messageClass'>" . htmlspecialchars($message) . "</div>";
        }
        ?>

        <form method="post">
            <div class="form-grid">
                <div>
                    <label for="album_title">Album</label>
                    <input
                        type="text"
                        name="album_title"
                        id="album_title"
                        value="<?php echo htmlspecialchars($albumTitle); ?>"
                        placeholder="Enter album title"
                        required
                    >
                </div>

                <div>
                    <label for="artist_name">Artist</label>
                    <input
                        type="text"
                        name="artist_name"
                        id="artist_name"
                        list="artist_list"
                        value="<?php echo htmlspecialchars($artistName); ?>"
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

            <h2>Tracks</h2>

            <div id="trackContainer">
                <?php
                $count = 1;
                foreach ($tracks as $track) {
                    ?>
                    <div class="track-box">
                        <h3>Track <?php echo $count; ?></h3>

                        <label>Track Name</label>
                        <input
                            type="text"
                            name="tracks[<?php echo $count - 1; ?>][name]"
                            value="<?php echo htmlspecialchars($track["name"]); ?>"
                            placeholder="Enter track name"
                        >

                        <label>Composer</label>
                        <input
                            type="text"
                            name="tracks[<?php echo $count - 1; ?>][composer]"
                            value="<?php echo htmlspecialchars($track["composer"]); ?>"
                            placeholder="Optional"
                        >
                    </div>
                    <?php
                    $count++;
                }
                ?>
            </div>

            <div class="button-row">
                <button type="button" class="btn btn-success" onclick="addTrackField()">Add Track</button>
                <button type="button" class="btn btn-secondary" onclick="removeTrackField()">Remove Track</button>
            </div>

            <br>
            <button type="submit" class="btn">Submit</button>
        </form>
    </div>
</div>

</body>
</html>
