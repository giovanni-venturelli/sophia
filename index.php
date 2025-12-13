<?php
// Ottieni la REQUEST_URI completa
$request = $_SERVER['REQUEST_URI'];

// Rimuovi la query string se presente
$request = strtok($request, '?');

// Rimuovi il base path (/test-route/)
$base_path = '/test-route/';
if (strpos($request, $base_path) === 0) {
    $request = substr($request, strlen($base_path));
}

// Rimuovi gli slash iniziali e finali
$request = trim($request, '/');

// DEBUG: vediamo cosa stiamo confrontando
echo "<!-- REQUEST_URI: " . $_SERVER['REQUEST_URI'] . " -->";
echo "<!-- Request pulito: '$request' -->";

?>
<html>
<head>
    <title>Router Test</title>
</head>
<body>
<?php
// Pattern che WordPress usa
$patterns = [
    '/^home\/([^\/]+)\/?$/' => 'home',
    '/^news\/([^\/]+)\/?$/' => 'news',
    '/^archive\/([^\/]+)\/?$/' => 'archive',
    '/^([^\/]+)\/?$/' => 'page',
    '/^(\d{4})\/(\d{2})\/?$/' => 'date archive',
    '/^$/' => 'homepage', // Aggiunto per gestire la homepage vuota
];

// Flag per sapere se abbiamo trovato un match
$found = false;

// Trova il match e carica il contenuto
foreach ($patterns as $pattern => $type) {
    if (preg_match($pattern, $request, $matches)) {
        echo "<p>✓ Match trovato! Tipo: <strong>$type</strong></p>";
        echo "<p>Request: '$request'</p>";
        echo "<p>Pattern: $pattern</p>";

        // Mostra i parametri catturati
        if (count($matches) > 1) {
            echo "<p>Parametri: ";
            print_r(array_slice($matches, 1));
            echo "</p>";
        }

        // Carica la pagina e passa i parametri
        $file = "pages/$type.php";
        if (file_exists($file)) {
            // I parametri catturati sono disponibili in $matches
            include $file;
        } else {
            echo "<p style='color: orange;'>⚠ File $file non trovato</p>";
        }

        $found = true;
        break; // IMPORTANTE: esci dal loop dopo il primo match
    }
}

// Se non abbiamo trovato nessun match
if (!$found) {
    echo "<p style='color: red;'>✗ Nessun pattern corrisponde a: '$request'</p>";
    echo "<h1>404 - Pagina non trovata</h1>";
}
?>

<hr>
<h3>Test dei link:</h3>
<ul>
    <li><a href="/test-route/">Homepage</a></li>
    <li><a href="/test-route/about">Pagina About</a></li>
    <li><a href="/test-route/contact">Pagina Contact</a></li>
    <li><a href="/test-route/home/articolo-1">Home con parametro</a></li>
    <li><a href="/test-route/news/notizia-1">News con parametro</a></li>
    <li><a href="/test-route/archive/2024">Archive con parametro</a></li>
    <li><a href="/test-route/2024/12">Date archive</a></li>
</ul>

</body>
</html>