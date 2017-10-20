<?php

namespace AppBundle\Console\Command;

use AppBundle\Imagine\Controller\ImagineController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class DeleteOldImagesCommand extends Command
{
    const MAX_FILES_LENGTH = 200000;

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
            $files = glob($imagesPath . '*.*');

            if ($this->isUsedDiskSpaceMax($maxPercentage) || count($files) > self::MAX_FILES_LENGTH) {
                $output->writeln('Used disk percentage is greater than ' . $maxPercentage . '% or files count is greater than ' . self::MAX_FILES_LENGTH);

                $exclude_files = array('.', '..', $imagesPath . ImagineController::DEFAULT_IMAGE_FILE);
                $files = array_diff($files, $exclude_files);

                // Sort files by accessed time, latest to earliest
                array_multisort(
                    array_map('fileatime', $files),
                    SORT_NUMERIC,
                    SORT_ASC,
                    $files
                );

                $fileCounter = 0;
                while ($this->isUsedDiskSpaceMax($maxPercentage) || count($files) > self::MAX_FILES_LENGTH) {
                    if (!isset($files[$fileCounter])) {
                        break;
                    }

                    $this->deleteThumbnailsAndFile($files[$fileCounter], $output);
                    unset($files[$fileCounter]);
                    $fileCounter++;
                }
            } else {
                $output->writeln('Used disk percentage is lesser than ' . $maxPercentage . '% and files count is lesser than ' . self::MAX_FILES_LENGTH);
                $output->writeln('No files will be deleted.');
            }
        }

        $output->writeln('Done.');
    }

    protected function isUsedDiskSpaceMax($maxPercentage)
    {
        $freeSpace = disk_free_space('/');
        $totalSpace = disk_total_space('/');
        $freePercentage = 100 * $freeSpace/$totalSpace;
        $usedPercentage = 100 - $freePercentage;

        return $usedPercentage >= $maxPercentage;
    }

    protected function deleteThumbnailsAndFile($filePath, OutputInterface $output)
    {
        $output->writeln("File to delete = '$filePath'");
        unlink($filePath);

        $imagesPath = ImagineController::IMAGES_PATH;
        $thumbnails = array();
        $thumbnails[] = str_replace(ImagineController::IMAGES_PATH, "media/cache/link_small/$imagesPath", $filePath);
        $thumbnails[] = str_replace(ImagineController::IMAGES_PATH, "media/cache/link_medium/$imagesPath", $filePath);
        $thumbnails[] = str_replace(ImagineController::IMAGES_PATH, "media/cache/link_big/$imagesPath", $filePath);

        foreach ($thumbnails as $thumbnail) {
            if (file_exists($thumbnail)) {
                $output->writeln("Thumbnail to delete = '$thumbnail'");
                unlink($thumbnail);
            }
        }
    }
}