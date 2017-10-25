<?php

namespace AppBundle\Console\Command;

use AppBundle\Imagine\Controller\ImagineController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class DeleteOldImagesCommand extends Command
{
    const MAX_FILES_LENGTH = 9900;

    protected function configure()
    {
        $this->setName('imagine:delete-images')
            ->setDescription('Deletes old images when used disk space is more than maxPercentage.')
            ->addOption('maxPercentage', 'maxPercentage', InputOption::VALUE_OPTIONAL, 'Max used disk space percentage', 80);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $maxPercentage = $input->getOption('maxPercentage');
        $output->writeln('Max Percentage = ' . $maxPercentage);

        $imagesPath = 'web/' . ImagineController::IMAGES_PATH;

        if (file_exists($imagesPath)) {
            $dirs = array_filter(glob($imagesPath . '*'), 'is_dir');

            if ($this->isUsedDiskSpaceMax($maxPercentage) || count($dirs) > self::MAX_FILES_LENGTH) {
                $output->writeln('Used disk percentage is greater than ' . $maxPercentage . '% or files count is greater than ' . self::MAX_FILES_LENGTH);
                $dirs = array_map('self::addFinalBarAndDot', $dirs);

                // Sort directories by accessed time, earliest to latest
                array_multisort(
                    array_map('filemtime', $dirs),
                    SORT_NUMERIC,
                    SORT_DESC,
                    $dirs
                );

                $dirs = array_map('self::removeFinal', $dirs);

                $fileCounter = 0;
                while ($this->isUsedDiskSpaceMax($maxPercentage) || count($dirs) > self::MAX_FILES_LENGTH) {
                    if (!isset($dirs[$fileCounter])) {
                        break;
                    }

                    $this->deleteThumbnailsAndFiles($dirs[$fileCounter], $output);
                    unset($dirs[$fileCounter]);
                    $fileCounter++;
                }
            } else {
                $output->writeln('Used disk percentage is lesser than ' . $maxPercentage . '% and files count is lesser than ' . self::MAX_FILES_LENGTH);
                $output->writeln('No files will be deleted.');
            }
        }

        $output->writeln('Done.');
    }

    protected function addFinalBarAndDot($dir)
    {
        return $dir . '/.';
    }

    protected function addFinalBar($dir)
    {
        return $dir . '/';
    }

    protected function addFinalDot($dir)
    {
        return $dir . '.';
    }

    protected function removeFinal($dir, $count = 1)
    {
        return substr($dir, 0, -$count);
    }

    protected function isUsedDiskSpaceMax($maxPercentage)
    {
        $freeSpace = disk_free_space('/');
        $totalSpace = disk_total_space('/');
        $freePercentage = 100 * $freeSpace/$totalSpace;
        $usedPercentage = 100 - $freePercentage;

        return $usedPercentage >= $maxPercentage;
    }

    protected function deleteThumbnailsAndFiles($dir, OutputInterface $output)
    {
        $subDirs =  array_filter(glob($dir . '*'), 'is_dir');
        $subDirs = array_map('self::addFinalBar', $subDirs);

        foreach ($subDirs as $subDir) {
            $exclude_files = array('.', '..');
            $files = glob($subDir . '*.*');
            $files = array_diff($files, $exclude_files);

            foreach ($files as $filePath) {
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln("File to delete = '$filePath'");
                }
                unlink($filePath);

                $imagesPath = ImagineController::IMAGES_PATH;
                $thumbnails = array();
                $thumbnails[] = str_replace(ImagineController::IMAGES_PATH, "media/cache/link_small/$imagesPath", $filePath);
                $thumbnails[] = str_replace(ImagineController::IMAGES_PATH, "media/cache/link_medium/$imagesPath", $filePath);
                $thumbnails[] = str_replace(ImagineController::IMAGES_PATH, "media/cache/link_big/$imagesPath", $filePath);

                foreach ($thumbnails as $thumbnail) {
                    if (file_exists($thumbnail)) {
                        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                            $output->writeln("Thumbnail to delete = '$thumbnail'");
                        }
                        unlink($thumbnail);
                    }
                }
            }
            @rmdir(str_replace(ImagineController::IMAGES_PATH, "media/cache/link_small/" . ImagineController::IMAGES_PATH, $subDir));
            @rmdir(str_replace(ImagineController::IMAGES_PATH, "media/cache/link_medium/" . ImagineController::IMAGES_PATH, $subDir));
            @rmdir(str_replace(ImagineController::IMAGES_PATH, "media/cache/link_big/" . ImagineController::IMAGES_PATH, $subDir));
            rmdir($subDir);
        }
        @rmdir(str_replace(ImagineController::IMAGES_PATH, "media/cache/link_small/" . ImagineController::IMAGES_PATH, $dir));
        @rmdir(str_replace(ImagineController::IMAGES_PATH, "media/cache/link_medium/" . ImagineController::IMAGES_PATH, $dir));
        @rmdir(str_replace(ImagineController::IMAGES_PATH, "media/cache/link_big/" . ImagineController::IMAGES_PATH, $dir));
        rmdir($dir);
    }
}