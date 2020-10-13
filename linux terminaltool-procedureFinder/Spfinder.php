#!/usr/bin/env php
<?php

/**
 * A tool that finds unused procedures by comparing the procedures you use in your software with those existing in your database.
 * 
 * @author Güney Çağlayan <guneycaglayann@gmail.com>
 */

class Spfinder
{

    public function main($argc = 0, $argv = [])
    {
        $usedProcedureList = [];
        foreach ($argv["path"] as $onePath) {
            $usedProcedureList = array_merge(
                $usedProcedureList,
                $this->procedureFinder("/home/guney/workspace/" . $onePath . "/src/service"));
            $usedProcedureList = array_merge(
                $usedProcedureList,
                $this->procedureFinder("/home/guney/workspace/" . $onePath . "/src/core/service/"));
        }

        $usedProcedureList = array_unique($usedProcedureList);
        $procedureList = $this->procedureList($argv["database"]);

        $unUsed = array_diff($procedureList, $usedProcedureList);
        $unDefined = array_diff($usedProcedureList, $procedureList);

        print_r($unUsed);
        print_r($unDefined);
        return 0;
    }

    private function procedureList(string $database): array
    {
        $procedures = [];
        $procedureDir = __DIR__ . "/" . $database . "/procedures";

        foreach (new \DirectoryIterator($procedureDir) as $fileInfo) {

            if ($fileInfo->isDir()) {
                continue;
            }
            $procedures[] = $fileInfo->getBasename(".sql");
        }
        return $procedures;
    }

    public function procedureFinder($appPath)
    {
        $sp = [];
        $merged = [];

        $serviceDir = $appPath;
        foreach (new \DirectoryIterator($serviceDir) as $dirInfo) {

            if (! $dirInfo->isDir()) {
                continue;
            }

            $controllerDir = $dirInfo->getPathname();
            foreach (new \DirectoryIterator($controllerDir) as $fileInfo) {

                if ($fileInfo->isDir()) {
                    continue;
                }

                $isInterface = false;
                $isDatabase = false;

                $handle = @fopen($fileInfo->getPathname(), "r");
                if (! $handle) {
                    echo "Cannot read file :" . $fileInfo->getPathname() . PHP_EOL;
                    continue;
                }

                while (($line = fgets($handle, 4096)) !== false) {

                    if (! $isDatabase) {

                        if (strpos($line, "interface " . $fileInfo->getBasename(".php")) !== false) {
                            $isInterface = true;
                            break;
                        }

                        if (strpos($line, "extends DatabaseService") !== false) {
                            $isDatabase = true;
                        }
                    } else {
                        $content = file_get_contents($fileInfo->getPathname());
                        $output = [];
                        preg_match_all('/PreparedStatement\([\s]*\'([^\']*)/', $content, $output);

                        $sp = $output[1];
                        break;
                    }
                }
                fclose($handle);

                if ($isInterface) {
                    continue;
                }

                if (! $isDatabase) {
                    continue;
                }
                $merged = array_unique(array_merge($merged, $sp));
            }
        }

        return $merged;
    }
}

$shortopt = "";
$longopt = [
    "path:",
    "database:"
];

$options = getopt($shortopt, $longopt);

$spfinder = new Spfinder();
$exitCode = $spfinder->main(count($options), $options);
exit($exitCode);