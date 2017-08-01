<?php

namespace BiffBangPow\DockerDiskUsage;

use Colors\Color;
use Docker\API\Model\ContainerInfo;
use Docker\Docker;
use Stringy\Stringy;

class DockerDiskUsage
{
    const BYTES_TO_GIGABYTES = 1024 * 1024 * 1024;

    const DETAILED_REPORT = true;
    const SUMMARY_REPORT = false;

    const FORMATTED_TEXT = true;
    const PLAIN_TEXT = false;

    const INCLUDE_MOUNTS = true;
    const EXCLUDE_MOUNTS = false;

    const NAME_WIDTH = 50;
    const STATUS_WIDTH = 17;
    const TABBED_WIDTH = self::ROUND_TO;
    const ROUND_TO = 3;

    /**
     * @var Color
     */
    private $cliFormatter;

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var Docker
     */
    private $docker;

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var array
     */
    private $images = [];

    /**
     * @var bool
     */
    private $includeMounts = self::EXCLUDE_MOUNTS;

    /**
     * @var bool
     */
    private $useTextFormatting = self::FORMATTED_TEXT;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->cliFormatter = new Color();
        $this->docker = new Docker();
    }

    /**
     * @param bool $levelOfDetail
     * @return string
     */
    public function plainTextReport(bool $levelOfDetail = self::SUMMARY_REPORT): string
    {
        $this->getDockerData();
        if ($levelOfDetail === self::DETAILED_REPORT) {
            $output = $this->createDetailedOutput();
        } else {
            $output = $this->createSummaryOutput();
        }
        $output .= $this->appendErrors();
        return $output;
    }

    /**
     * @return void
     */
    public function disableTextFormatting()
    {
        $this->useTextFormatting = self::PLAIN_TEXT;
    }

    /**
     * @return void
     */
    public function enableIncludeMounts()
    {
        $this->includeMounts = self::INCLUDE_MOUNTS;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return string
     */
    private function createSummaryOutput(): string
    {
        $totalDiskUsage = 0;

        $output = $this->lineBreak();

        foreach ($this->data as $applicationName => $applicationData) {

            $containerDiskUsage = 0;
            $volumeDiskUsage = 0;
            $mountsDiskUsage = 0;
            $totalApplicationDiskUsage = 0;

            if (isset($applicationData['containers'])) {
                foreach ($applicationData['containers'] as $container) {
                    $containerDiskUsage += $container['diskUsage'];
                }
                $totalApplicationDiskUsage += $containerDiskUsage;
            }

            if (isset($applicationData['volumes'])) {
                foreach ($applicationData['volumes'] as $volume) {
                    $volumeDiskUsage += $volume['diskUsage'];
                }
                $totalApplicationDiskUsage += $volumeDiskUsage;
            }

            if (isset($applicationData['mounts'])) {
                foreach ($applicationData['mounts'] as $mountPath => $mountSize) {
                    $mountsDiskUsage += $mountSize;
                }
                $totalApplicationDiskUsage += $mountsDiskUsage;
            }

            $output .= $this->bold($this->yellow(
                $this->padApplicationName($applicationName) . $this->padDiskUsage($totalApplicationDiskUsage, '-')
            ));
            $output .= $this->lineBreak(2);
            $output .= ' Containers:  ' . $this->padDiskUsage($containerDiskUsage) . $this->lineBreak();
            $output .= ' Volumes:     ' . $this->padDiskUsage($volumeDiskUsage) . $this->lineBreak();
            if ($this->includeMounts == true) {
                $output .= ' Mounts:      ' . $this->padDiskUsage($mountsDiskUsage) . $this->lineBreak();
            }
            $output .= $this->lineBreak();

            $totalDiskUsage += $totalApplicationDiskUsage;
        }

        $totalImageDiskUsage = 0;
        foreach ($this->images as $imageId => $image) {
            $totalImageDiskUsage += $image['diskUsage'];
        }
        $totalDiskUsage += $totalImageDiskUsage;
        $output .= $this->bold($this->yellow(
            $this->padApplicationName('Images (shared)') . $this->padDiskUsage($totalImageDiskUsage, '-')
        ));
        $output .= $this->lineBreak(2);

        $output .= $this->bold($this->yellow(
            $this->padApplicationName('Combined Total') . $this->padDiskUsage($totalDiskUsage, '-')
        ));
        $output .= $this->lineBreak(2);

        return $output;
    }

    /**
     * @return string
     */
    private function createDetailedOutput(): string
    {
        $totalDiskUsage = 0;

        $output = $this->lineBreak();

        foreach ($this->data as $applicationName => $applicationData) {

            $containersOutput = '';
            $volumesOutput = '';
            $totalApplicationDiskUsage = 0;

            if (isset($applicationData['containers'])) {
                $containersOutput = ' Containers...' . $this->lineBreak();
                foreach ($applicationData['containers'] as $container) {
                    $containersOutput .= '   ' . $this->padName($container['name']) .
                        $this->padStatus($container['status']) .
                        $this->padDiskUsage($container['diskUsage']) .
                        $this->lineBreak();
                    $totalApplicationDiskUsage += $container['diskUsage'];
                }
            }

            if (isset($applicationData['volumes'])) {
                $volumesOutput = ' Volumes...' . $this->lineBreak();
                foreach ($applicationData['volumes'] as $volume) {
                    $volumesOutput .= '   ' . $this->padNameToFillStatusColumn($volume['name']) .
                        $this->padDiskUsage($volume['diskUsage']) .
                        $this->lineBreak();
                    $totalApplicationDiskUsage += $volume['diskUsage'];
                }
            }

            $mountsOutput = '';
            if (isset($applicationData['mounts']) && count($applicationData['mounts']) > 0) {
                $mountsOutput = ' Mounts...' . $this->lineBreak();
                foreach ($applicationData['mounts'] as $mountPath => $mountSize) {
                    $truncatedMountPath = $this->centreTruncateString($mountPath, 55);
                    $mountsOutput .= '   ' . $this->padNameToFillStatusColumn($truncatedMountPath) .
                        $this->padDiskUsage($mountSize) .
                        $this->lineBreak();
                    $totalApplicationDiskUsage += $mountSize;
                }
                $mountsOutput .=  $this->lineBreak();
            }

            $output .= $this->bold($this->yellow(
                $this->padApplicationName($applicationName) . $this->padDiskUsage($totalApplicationDiskUsage, '-')
            ));
            $output .= $this->lineBreak(2);
            $output .= $containersOutput . $this->lineBreak();
            $output .= $volumesOutput . $this->lineBreak();
            if ($this->includeMounts === true) {
                $output .= $mountsOutput;
            }
            $output .= $this->lineBreak();

            $totalDiskUsage += $totalApplicationDiskUsage;
        }

        $totalImageDiskUsage = 0;
        $imagesOutput = '';
        foreach ($this->images as $imageId => $image) {
            $imageNameOrId = $this->centreTruncateString(
                $image['name'] != '' ? $image['name'] : $imageId,
                55
            );
            $imagesOutput .= '   ' . $this->padNameToFillStatusColumn($imageNameOrId) .
                $this->padDiskUsage($image['diskUsage']) .
                $this->lineBreak();
            $totalImageDiskUsage += $image['diskUsage'];
        }
        $totalDiskUsage += $totalImageDiskUsage;

        $output .= $this->bold($this->yellow(
            $this->padApplicationName('IMAGES (shared)') . $this->padDiskUsage($totalImageDiskUsage, '-')
        ));
        $output .= $this->lineBreak(2);

        $output .= $imagesOutput . $this->lineBreak(2);

        $output .= $this->bold($this->yellow(
            $this->padApplicationName('Combined Total') . $this->padDiskUsage($totalDiskUsage, '-')
        ));
        $output .= $this->lineBreak(2);

        return $output;
    }

    /**
     * @return string
     */
    private function appendErrors(): string
    {
        if (count($this->errors) === 0) {
            return '';
        }

        $output = $this->bold($this->red('Errors...'));
        $output .= $this->lineBreak(2);
        foreach ($this->errors as $error) {
            $output .= $this->red($error);
            $output .= $this->lineBreak(2);
        }

        return $output;
    }

    /**
     * @return void
     */
    private function getDockerData()
    {
        $this->getContainerData();
        $this->getVolumeData();
        $this->getImageData();
        ksort($this->data);
    }

    /**
     * @return void
     */
    private function getContainerData()
    {
        $containers = $this->docker->getContainerManager()->findAll(['size' => true]);
        foreach ($containers as $container) {
            // Apparently it's possible for containers to have multiple names.
            // @WillGibson has not come across this before, but we may need to address it at some point.
            $containerNameFull = Stringy::create($container->getNames()[0])->substr(1);
            $containerNameParts = explode('_', $containerNameFull, 2);
            $applicationName = $containerNameParts[0];
            $containerName = $containerNameParts[1];
            $this->data[$applicationName]['containers'][$container->getId()] = [
                'name'      => $containerName,
                'status'    => $container->getStatus(),
                'diskUsage' => $this->bytesToGigabytes($container->getSizeRw() + $container->getSizeRootFs()),
            ];
            if ($this->includeMounts === true) {
                $this->data[$applicationName]['mounts'] = $this->getMountsData($container);
            }
        }
    }

    /**
     * @return void
     */
    private function getVolumeData()
    {
        $volumes = $this->docker->getVolumeManager()->findAll()->getVolumes();
        if (is_array($volumes) === false) {
            return;
        }
        foreach ($volumes as $volume) {
            $volumeNameFull = $volume->getName();
            $volumeNameParts = explode('_', $volumeNameFull, 2);
            $applicationName = $volumeNameParts[0];
            $volumeName = $volumeNameParts[1];
            $this->data[$applicationName]['volumes'][$volumeNameFull] = [
                'name'      => $volumeName,
                'diskUsage' => $this->getFilePathSize($volume->getMountpoint()),
            ];
        }
    }

    /**
     * @return void
     */
    private function getImageData()
    {
        $images = $this->docker->getImageManager()->findAll();
        foreach ($images as $image) {
            $imageId = $image->getId();
            $repoTags = $image->getRepoTags();
            $name = is_array($repoTags) ? implode(',', $repoTags) : $imageId;
            $this->images[$imageId] = [
                'name'      => $name,
                'diskUsage' => $this->bytesToGigabytes($image->getSize()),
            ];
        }
    }

    /**
     * @param ContainerInfo $container
     * @return array
     */
    private function getMountsData(ContainerInfo $container)
    {
        $applicationMounts = [];
        // It would be nice to do it like this commented out bit, but \Docker\API\Normalizer\MountNormalizer
        // and \Docker\API\Model\Mount do not deal with Type yet. @WillGibson will look at submitting a PR for that.
        // https://github.com/docker-php/docker-php/issues/251
        // foreach ($container->getMounts() as $mount) {
        //     if ($mount->getType() !== 'volume') {
        //         $source = $mount->getSource();
        //         if (array_key_exists($source, $applicationMounts) === false) {
        //             $applicationMounts[$source] = $this->getFilePathSize($source);
        //         }
        //     }
        // }
        $containerData = $this->inspectContainer($container->getId());
        foreach ($containerData['Mounts'] as $mount) {
            if ($mount['Type'] !== 'volume') {
                $source = $mount['Source'];
                if (array_key_exists($source, $applicationMounts) === false) {
                    $applicationMounts[$source] = $this->getFilePathSize($source);
                }
            }
        }
        return $applicationMounts;
    }

    /**
     * @param string $applicationName
     * @return string
     */
    private function padApplicationName(string $applicationName): string
    {
        $padded = Stringy::create($applicationName)
            ->toUpperCase()
            ->padRight(self::NAME_WIDTH + self::STATUS_WIDTH + self::TABBED_WIDTH, '-')
        ;
        return $padded;
    }

    /**
     * @param string $name
     * @return string
     */
    private function padName(string $name): string
    {
        return Stringy::create($name)->padRight(self::NAME_WIDTH);
    }

    /**
     * @param string $name
     * @return string
     */
    private function padNameToFillStatusColumn(string $name): string
    {
        return Stringy::create($name)->padRight(self::NAME_WIDTH + self::STATUS_WIDTH);
    }

    /**
     * @param string $status
     * @return string
     */
    private function padStatus(string $status): string
    {
        return Stringy::create($status)->padRight(self::STATUS_WIDTH);
    }

    /**
     * @param string $diskUsage
     * @param string $padStr
     * @return string
     */
    private function padDiskUsage(string $diskUsage, string $padStr = ' '): string
    {
        return Stringy::create(number_format($diskUsage, 2))->padLeft(9, $padStr)->append('GB');
    }

    /**
     * @param string $text
     * @return string
     */
    private function bold(string $text): string
    {
        if ($this->useTextFormatting === false) {
            return $text;
        }
        return (string)$this->cliFormatter->apply('bold', $text);
    }

    /**
     * @param string $text
     * @return string
     */
    private function yellow(string $text): string
    {
        if ($this->useTextFormatting === false) {
            return $text;
        }
        return (string)$this->cliFormatter->fg('yellow', $text);
    }

    /**
     * @param string $text
     * @return string
     */
    private function red(string $text): string
    {
        if ($this->useTextFormatting === false) {
            return $text;
        }
        return (string)$this->cliFormatter->fg('red', $text);
    }

    /**
     * @param string $containerId
     * @return array
     */
    private function inspectContainer(string $containerId): array
    {
        $containerDataJSON = shell_exec('docker inspect --size ' . $containerId);
        return json_decode($containerDataJSON, true)[0];
    }

    /**
     * @param string $filePath
     * @return float
     */
    private function getFilePathSize(string $filePath): float
    {
        exec(
            "du --bytes --max-depth=0 " . $filePath,
            $filePathSizeRaw,
            $returnCode
        );
        if ($returnCode !== 0) {
            $this->errors[] = 'Unable to get size of ' . $filePath . '. You probably need to run this as root.';
        }
        $filePathSizeInBytes = (int)explode(' ', implode($filePathSizeRaw), 2)[0];
        return round(
            ($filePathSizeInBytes / self::BYTES_TO_GIGABYTES),
            self::ROUND_TO
        );
    }

    /**
     * @param string $text
     * @param int $maxLength
     * @return string
     */
    private function centreTruncateString(string $text, int $maxLength): string
    {
        $textLength = strlen($text);
        if ($textLength <= $maxLength) {
            return $text;
        } else {
            return substr_replace($text, '...', $maxLength / 2, $textLength - $maxLength);
        }
    }

    /**
     * @param int $bytes
     * @return float
     */
    private function bytesToGigabytes(int $bytes): float
    {
        $imageDiskUsage = round(
            ($bytes / self::BYTES_TO_GIGABYTES),
            self::ROUND_TO
        );
        return $imageDiskUsage;
    }

    /**
     * @param int $numLines
     * @return string
     */
    private function lineBreak(int $numLines = 1): string
    {
        $output = PHP_EOL;
        for ($x = 2; $x <= $numLines; $x++) {
            $output .= PHP_EOL;
        }
        return $output;
    }
}
