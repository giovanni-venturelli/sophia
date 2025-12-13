<h1>Home</h1>
<p>ID ricevuto: <strong><?php if (!empty($matches)) {
            echo htmlspecialchars($matches[1]);
        } ?></strong></p>

<?php
// Usa l'ID per fare quello che ti serve
$id = intval($matches[1]); // Converti in intero per sicurezza

// Esempio: query al database
echo "<p>Caricamento post con ID: $id</p>";

// Oppure include un altro file passando l'ID
// include "components/post.php";
?>