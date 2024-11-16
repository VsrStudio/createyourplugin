<?php
function createLog($pluginName, $logMessage) {
    $logDir = "logs";
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = "$logDir/{$pluginName}_creation.log";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $logMessage\n", FILE_APPEND);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pluginName = htmlspecialchars($_POST["pluginName"]);
    $authorName = htmlspecialchars($_POST["authorName"]);
    $version = htmlspecialchars($_POST["version"]);
    $apiVersion = htmlspecialchars($_POST["apiVersion"]);
    $namespace = htmlspecialchars($_POST["namespace"]) ?: $pluginName;
    $description = htmlspecialchars($_POST["description"]);
    $commands = isset($_POST["commands"]) ? htmlspecialchars($_POST["commands"]) : '';
    $dependencies = isset($_POST["dependencies"]) ? htmlspecialchars($_POST["dependencies"]) : '';
    $events = isset($_POST["events"]) ? $_POST["events"] : [];
    $license = isset($_POST["license"]) ? htmlspecialchars($_POST["license"]) : '';

    // Validasi input
    if (empty($pluginName) || empty($authorName) || empty($version) || empty($apiVersion)) {
        die("Semua field wajib diisi. Silakan coba lagi.");
    }

    createLog($pluginName, "Memulai pembuatan plugin.");

    // Direktori output untuk plugin
    $pluginDir = "output/" . $pluginName;
    
    if (!file_exists($pluginDir)) {
        mkdir($pluginDir, 0777, true);
        mkdir($pluginDir . "/src/$namespace", 0777, true);
        createLog($pluginName, "Struktur folder dibuat.");
    }

    // Menangani unggahan ikon
    if (isset($_FILES['icon']) && $_FILES['icon']['error'] == UPLOAD_ERR_OK) {
        $iconPath = $pluginDir . "/icon.png";
        move_uploaded_file($_FILES['icon']['tmp_name'], $iconPath);
        createLog($pluginName, "Ikon diunggah ke $iconPath.");
    }

    // Membuat file plugin.yml
    $pluginYmlContent = "name: $pluginName
main: $namespace\\Main
version: $version
api: $apiVersion
author: $authorName
description: $description
";
    if (!empty($commands)) {
        $pluginYmlContent .= "commands:\n";
        $commandsArray = explode("\n", $commands);
        foreach ($commandsArray as $command) {
            list($cmd, $desc) = explode(" - ", $command);
            $pluginYmlContent .= "  $cmd:\n    description: $desc\n";
        }
    }
    if (!empty($dependencies)) {
        $pluginYmlContent .= "depend: [" . str_replace(", ", ",", $dependencies) . "]\n";
    }
    if (!empty($license)) {
        $pluginYmlContent .= "license: $license\n";
    }

    file_put_contents("$pluginDir/plugin.yml", $pluginYmlContent);
    createLog($pluginName, "plugin.yml dibuat.");

    // Membuat file Main.php dengan event listener
    $mainPhpContent = "<?php
namespace $namespace;

use pocketmine\\plugin\\PluginBase;
use pocketmine\\event\\Listener;
";
    foreach ($events as $event) {
        $mainPhpContent .= "use $event;\n";
    }

    $mainPhpContent .= "
class Main extends PluginBase implements Listener {
    public function onEnable(): void {
        \$this->getServer()->getPluginManager()->registerEvents(\$this, \$this);
        \$this->getLogger()->info(\"$pluginName diaktifkan!\");
    }

    public function onDisable(): void {
        \$this->getLogger()->info(\"$pluginName dinonaktifkan!\");
    }
";

    foreach ($events as $event) {
        $eventShortName = end(explode("\\", $event));
        $mainPhpContent .= "
    public function on$eventShortName($event \$event): void {
        // Tambahkan logika untuk $eventShortName di sini
    }
";
    }

    $mainPhpContent .= "}
";
    
    file_put_contents("$pluginDir/src/$namespace/Main.php", $mainPhpContent);
    createLog($pluginName, "Main.php dibuat.");

    // Membuat file EventListener.php
    $eventListenerContent = "<?php
namespace $namespace;

use pocketmine\\event\\Listener;

class EventListener implements Listener {
    public function __construct() {
        // Inisialisasi
    }

    // Tambahkan metode event di sini
}
";
    file_put_contents("$pluginDir/src/$namespace/EventListener.php", $eventListenerContent);
    createLog($pluginName, "EventListener.php dibuat.");

    // Membuat file CommandHandler.php jika ada commands
    if (!empty($commands)) {
        $commandHandlerContent = "<?php
namespace $namespace;

use pocketmine\\command\\Command;
use pocketmine\\command\\CommandSender;
use pocketmine\\player\\Player;

class CommandHandler extends Command {
    public function __construct(string \$name) {
        parent::__construct(\$name);
        \$this->setDescription('Command handler untuk plugin.');
    }

    public function execute(CommandSender \$sender, string \$label, array \$args): void {
        if (\$sender instanceof Player) {
            \$sender->sendMessage('Command dieksekusi!');
        }
    }
}
";
        file_put_contents("$pluginDir/src/$namespace/CommandHandler.php", $commandHandlerContent);
        createLog($pluginName, "CommandHandler.php dibuat.");
    }

    // Membuat file .zip untuk diunduh
    $zip = new ZipArchive();
    $zipFile = "output/$pluginName.zip";

    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen("output/"));
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $zip->close();
        createLog($pluginName, "File ZIP berhasil dibuat.");
        echo "Plugin $pluginName telah dibuat. <a href='$zipFile'>Unduh di sini</a>";
    } else {
        createLog($pluginName, "Gagal membuat file ZIP.");
        echo "Gagal membuat file ZIP.";
    }
}
?>
