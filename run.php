<?php
################################################################
# Déclaration de la variable globale pour le chemin de sortie
################################################################
$GLOBALS['outFilePath'] ='D:/Flux/Scaner/LOGILEC_PROD/emission/';
###########################################################
# Mode débogage - Active ou désactive les messages de log
#Mettre à "true" pour activer le mode debug
###########################################################
$GLOBALS['debug'] = false;

if ($GLOBALS['debug'] = true) {
    echo "###########################################################\n";
    echo "Mode debug activé - Les logs seront affichés en local.\n";
    echo "###########################################################\n";
} 




###########################################################
# Fonction pour charger et nettoyer un fichier XML
###########################################################
function loadXmlFile($xmlFilePath) {
    $xml = file_get_contents($xmlFilePath);
    $xml = trim($xml);
    if (!mb_check_encoding($xml, 'UTF-8')) {
        $xml = mb_convert_encoding($xml, 'UTF-8', 'ISO-8859-1'); 
    }
    try {
        $xmlObject = new SimpleXMLElement($xml);
    } catch (Exception $e) {
        logMessage("Erreur lors de la création de SimpleXMLElement: " . $e->getMessage(), 'error');
        return null;
    }
    return $xmlObject;
}

###########################################################
# Fonction pour modifier l'élément XML spécifique
###########################################################
function modifyXml($xmlObject) {
    try {
        if (isset($xmlObject->Body->INVOIC->g002[2]->NAD->cmp01->e01_3039[0] )
            and isset($xmlObject->Body->INVOIC->g002[3]->NAD->cmp01)
            and isset($xmlObject->Body->INVOIC->g002[3]->NAD->cmp01->e03_3055)) {
            $data = $xmlObject->Body->INVOIC->g002[2]->NAD->cmp01->e01_3039[0];
            $parentElement = $xmlObject->Body->INVOIC->g002[3]->NAD->cmp01;
            $existingE03_3055 = (string)$parentElement->e03_3055;
            unset($parentElement->e03_3055);
            $parentElement->addChild('e01_3039', (string)$data);
            $parentElement->addChild('e03_3055', $existingE03_3055);
        }
        return $xmlObject;
    } catch (Exception $e) {
        logMessage("Exception capturée dans modifyXml: " . $e->getMessage(), 'error');
    }

    return null;
}

#####################################################################
# Fonction pour sauvegarder le fichier modifié dans le dossier OUT
#####################################################################
function saveModifiedXml($xmlObject, $originalFilePath) {
    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xmlObject->asXML());
    $pathInfo = pathinfo($originalFilePath);
    if ($GLOBALS['debug'] = false) {
        $outputDir = './OUT/';
    } else {
        $outputDir = $GLOBALS['outFilePath'];
    }
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true); // 0777
    }
    $outputFileName = $outputDir . $pathInfo['filename'] . '-edited.xml';
    $dom->save($outputFileName);
    return $outputFileName;
}
#####################################################################
# Fonction pour décompresser les fichiers ZIP
#####################################################################
function unzipFiles($zipFilePath, $extractTo) {
    $zip = new ZipArchive;
    if ($zip->open($zipFilePath) === TRUE) {
        $zip->extractTo($extractTo);
        $zip->close();
    } else {
        logMessage("Impossible d'ouvrir le fichier ZIP: " . $zipFilePath, 'error');
    }
}

#####################################################################
# Fonction pour traiter tous les fichiers XML dans un répertoire
#####################################################################
function processXmlFiles($directory) {
    $files = glob($directory . '*.xml');
    $modifiedFiles = [];
    if (empty($files)) {
        return [];
    } else {
        foreach ($files as $file) {
            $xmlObject = loadXmlFile($file);
            if ($xmlObject) {
                $modifiedXml = modifyXml($xmlObject);
                if ($modifiedXml) {
                    $modifiedFile = saveModifiedXml($modifiedXml, $file);
                    if ($modifiedFile) {
                        $modifiedFiles[] = $modifiedFile;
                    }
                }
            }
        }
    }
    return $modifiedFiles;
}

#####################################################################
# Fonction pour traiter les fichiers ZIP et XML dans le dossier IN
#####################################################################
function processZipAndXmlFiles() {
    $inputDir = './IN/';
    $tempDir = './TEMP/';
    if (!is_dir($tempDir)) {
        mkdir($tempDir);
    }

    $allFiles = glob($inputDir . '*');
    $zipFiles = glob($inputDir . '*.zip');
    $nonZipFiles = array_diff($allFiles, $zipFiles);
    if($GLOBALS['debug']) {
        var_dump($allFiles);
    }

    if (!empty($nonZipFiles)) {
        if($GLOBALS['debug']) {
            var_dump($nonZipFiles);
        }

        foreach ($nonZipFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'xml') {
                processXmlFile($file, $tempDir);
            } else {
                logMessage("Fichier non ZIP détecté : " . basename($file), 'error');
            }
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    foreach ($zipFiles as $zipFile) {
        unzipFiles($zipFile, $tempDir);
        if (is_file($zipFile)) {
            unlink($zipFile);
        }
    }

    $modifiedFiles = processXmlFiles($tempDir);
    array_map('unlink', glob("$tempDir*"));
}




function processXmlFile($file, $tempDir) {
    copy($file, $tempDir . basename($file));
}


#####################################################################
# Fonction pour enregistrer des messages dans un fichier log
#####################################################################
function logMessage($message, $type = 'info') {
    $logFile = __DIR__ . '/' . ($type === 'error' ? 'error_log.txt' : 'info_log.txt');
    $currentDate = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$currentDate] " . strtoupper($type) 
	.": $message" . PHP_EOL, FILE_APPEND);
}

#####################################################################
# Exécuter le traitement des fichiers ZIP et XML
#####################################################################
processZipAndXmlFiles();

?>

