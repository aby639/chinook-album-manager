<?php
include "db.php";

/*
    index.php

    This is the homepage of the Chinook Album Manager.
    It shows the total number of albums, artists and tracks.
    It also displays the main album list with search,
    details, update and delete options.
*/

$search = "";
if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
}
$searchSafe = $conn->real_escape_string($search);

$totalAlbums = 0;
$totalArtists = 0;
$totalTracks = 0;

$resultCountAlbums = $conn->query("SELECT COUNT(*) AS totalAlbums FROM albums");
if ($resultCountAlbums) {
    $rowCountAlbums = $resultCountAlbums->fetch_assoc();
    $totalAlbums = $rowCountAlbums["totalAlbums"];
}

$resultCountArtists = $conn->query("SELECT COUNT(*) AS totalArtists FROM artists");
if ($resultCountArtists) {
    $rowCountArtists = $resultCountArtists->fetch_assoc();
    $totalArtists = $rowCountArtists["totalArtists"];
}

$resultCountTracks = $conn->query("SELECT COUNT(*) AS totalTracks FROM tracks");
if ($resultCountTracks) {
    $rowCountTracks = $resultCountTracks->fetch_assoc();
    $totalTracks = $rowCountTracks["totalTracks"];
}
// Load albums with their artist names for the main table
$sql = "SELECT albums.AlbumId, albums.Title, artists.Name AS ArtistName
        FROM albums
        INNER JOIN artists ON albums.ArtistId = artists.ArtistId";

if ($search != "") {
    $sql .= " WHERE albums.Title LIKE '%$searchSafe%'
              OR artists.Name LIKE '%$searchSafe%'";
}

$sql .= " ORDER BY albums.AlbumId DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chinook Album Manager</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="background-overlay"></div>

<div class="container">

    <section class="hero-section">
        <div class="hero-text">
            <p class="hero-kicker">Music Library Dashboard</p>
            <h1>Chinook Album Manager</h1>
            <p>
                View, insert, update and delete albums together with their related
                artist and track information in one simple music management system.
            </p>

            <div class="hero-buttons">
                <a href="create.php" class="btn">Insert New Album</a>
                <a href="#album-list" class="btn btn-secondary">Browse Records</a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="music-panel">
                <div class="music-note-group">
                    <span class="note note-one">&#9835;</span>
                    <span class="note note-two">&#9833;</span>
                    <span class="note note-three">&#9834;</span>
                </div>

                <div class="music-bars">
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="music-panel-text">
                    <h2>Manage Music Records</h2>
                    <p>Albums - Artists - Tracks</p>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total Albums</div>
            <h2><?php echo $totalAlbums; ?></h2>
            <p>Stored in the Chinook library</p>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Artists</div>
            <h2><?php echo $totalArtists; ?></h2>
            <p>Connected to album records</p>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Tracks</div>
            <h2><?php echo $totalTracks; ?></h2>
            <p>Available across all albums</p>
        </div>
    </section>

    <section class="card" id="album-list">

        <?php if (isset($_GET["deleted"])) { ?>
            <div class="message success auto-hide">
                <?php
                if (isset($_GET["album"]) && trim($_GET["album"]) != "") {
                    echo "Album \"" . htmlspecialchars($_GET["album"]) . "\" deleted successfully.";
                } else {
                    echo "Album deleted successfully.";
                }
                ?>
            </div>
        <?php } ?>

        <?php if (isset($_GET["created"])) { ?>
            <div class="message success auto-hide">
                <?php
                if (isset($_GET["album"]) && trim($_GET["album"]) != "") {
                    echo "Album \"" . htmlspecialchars($_GET["album"]) . "\" created successfully.";
                } else {
                    echo "Album created successfully.";
                }
                ?>
            </div>
        <?php } ?>

        <div class="library-header">
            <div class="library-title">
                <h2>Albums and Artists</h2>
                <p class="sub-text">View existing album records and manage related information.</p>
            </div>

            <form method="get" class="search-box">
                <input
                    type="text"
                    name="search"
                    placeholder="Search by album or artist..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >
                <button type="submit" class="btn">Search</button>
                <a href="index.php#album-list" class="btn btn-secondary">Reset</a>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="nowrap-col">Album ID</th>
                        <th>Album</th>
                        <th>Artist</th>
                        <th class="nowrap-col">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='nowrap-col'>" . $row["AlbumId"] . "</td>";
                            echo "<td>" . htmlspecialchars($row["Title"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["ArtistName"]) . "</td>";
                            echo "<td class='action-links nowrap-col'>
                                    <a class='details' href='details.php?id=" . $row["AlbumId"] . "'>Details</a>
                                    <a class='update' href='update.php?id=" . $row["AlbumId"] . "'>Update</a>
                                    <a class='delete' href='delete.php?id=" . $row["AlbumId"] . "'>Delete</a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No albums found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <p class="footer-note">Chinook database album management system</p>
    </section>

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
