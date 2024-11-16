<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Mendapatkan data dari form
    $pluginName = $_POST['pluginName'];
    $authorName = $_POST['authorName'];
    $version = $_POST['version'];
    $apiVersion = $_POST['apiVersion'];
    $description = $_POST['description'];
    $license = $_POST['license'];

    // Folder tempat plugin akan disimpan
    $pluginFolder = "plugins/$pluginName";
    if (!file_exists($pluginFolder)) {
        mkdir($pluginFolder, 0777, true);
    }

    // Membuat file plugin.yml
    $pluginYmlContent = "
name: $pluginName
version: $version
api: $apiVersion
author: $authorName
description: $description
license: $license
";
    file_put_contents("$pluginFolder/plugin.yml", $pluginYmlContent);

    // Menyimpan file ikon (jika diunggah)
    if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
        $iconPath = "uploads/" . $_FILES['icon']['name'];
        move_uploaded_file($_FILES['icon']['tmp_name'], $iconPath);
    }

    // Menambahkan plugin ke folder
    $zip = new ZipArchive();
    $zipFile = "$pluginFolder.zip";
    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
        // Menambahkan seluruh folder plugin ke file zip
        $this->addDirToZip($pluginFolder, $zip);
        $zip->close();
    }

    // Tampilkan pesan sukses
    echo "Plugin telah berhasil dibuat! <a href='$zipFile'>Unduh Plugin</a>";
}

// Fungsi untuk menambahkan folder ke dalam file ZIP
function addDirToZip($dir, $zip, $zipdir = "") {
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            addDirToZip($path, $zip, "$zipdir$file/");
        } else {
            $zip->addFile($path, "$zipdir$file");
        }
    }
}
?>
