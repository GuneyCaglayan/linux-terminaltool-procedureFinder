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
        $shortopt = "";
        $longopt = [
            "path:",
            "database:"
        ];

        $options = getopt($shortopt, $longopt);
        $appPath = $options["path"];
        $procedureDir = $options["database"];

        $usedProcedureList = [];
        foreach ($appPath as $onePath) {
            $usedProcedureList = array_merge($usedProcedureList, $this->procedureFinder($onePath));
        }
        $usedProcedureList = array_unique($usedProcedureList);
        $procedureList = $this->procedureList($procedureDir);

        $unUsed = array_diff($procedureList, $usedProcedureList);
        $unDefined = array_diff($usedProcedureList, $procedureList);

        print_r($unUsed);
        print_r($unDefined);
    }

    public function procedureList($procedureDir)
    {
        $procedures = [];
        foreach (new \DirectoryIterator($procedureDir) as $dirInfo) {
            $procedures[] = $dirInfo->getBasename(".sql");
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
$spfinder = new Spfinder();
$spfinder->main();