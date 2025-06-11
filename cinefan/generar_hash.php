<?php
$passwordQueQuieresUsar = 'root';

// Generar el hash
$hash = password_hash($passwordQueQuieresUsar, PASSWORD_DEFAULT);

// Imprimir el hash
echo "Copia y pega este hash en tu consulta SQL:<br><br>";
echo $hash;
?>