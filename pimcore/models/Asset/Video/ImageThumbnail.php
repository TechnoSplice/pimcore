<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @category   Pimcore
 * @package    Asset
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Asset\Video;

use Pimcore\Model\Asset\Image;
use Pimcore\Model;
use Pimcore\File;

class ImageThumbnail
{

    /**
     * @var \Pimcore\Model\Asset\Video
     */
    protected $asset;

    /**
     * @var mixed|string
     */
    protected $filesystemPath;

    /**
     * @var int
     */
    protected $width;

    /**
     * @var int
     */
    protected $height;

    /**
     * @var int
     */
    protected $realWidth;

    /**
     * @var int
     */
    protected $realHeight;

    /**
     * @var Image\Thumbnail\Config
     */
    protected $config;

    /**
     * @var
     */
    protected $timeOffset;

    /**
     * @var
     */
    protected $imageAsset;

    /**
     * ImageThumbnail constructor.
     * @param $asset
     * @param null $config
     * @param null $timeOffset
     * @param null $imageAsset
     */
    public function __construct($asset, $config = null, $timeOffset = null, $imageAsset = null)
    {
        $this->asset = $asset;
        $this->timeOffset = $timeOffset;
        $this->imageAsset = $imageAsset;
        $this->config = $this->createConfig($config);
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        $fsPath = $this->getFileSystemPath();
        $path = str_replace(PIMCORE_DOCUMENT_ROOT, "", $fsPath);

        $results = \Pimcore::getEventManager()->trigger("frontend.path.asset.video.image-thumbnail", $this, [
            "filesystemPath" => $fsPath,
            "frontendPath" => $path
        ]);

        if ($results->count()) {
            $path = $results->last();
        }

        return $path;
    }

    /**
     * @return mixed|string
     */
    public function getFileSystemPath()
    {
        $this->generate();
        return $this->filesystemPath;
    }

    /**
     *
     */
    public function generate()
    {
        $errorImage = PIMCORE_PATH . '/static6/img/filetype-not-supported.png';

        if (!$this->asset) {
            $this->filesystemPath = $errorImage;
        } elseif (!$this->filesystemPath) {
            $cs = $this->asset->getCustomSetting("image_thumbnail_time");
            $im = $this->asset->getCustomSetting("image_thumbnail_asset");

            if ($im || $this->imageAsset) {
                if ($this->imageAsset) {
                    $im = Model\Asset::getById($im);
                }

                if ($im instanceof Image) {
                    return $im->getThumbnail($this->getConfig());
                }
            }

            $timeOffset = $this->timeOffset;
            if (!$this->timeOffset && $cs) {
                $timeOffset = $cs;
            }

            // fallback
            if (!$timeOffset) {
                $timeOffset = ceil($this->asset->getDuration() / 3);
            }

            $converter = \Pimcore\Video::getInstance();
            $converter->load($this->asset->getFileSystemPath());
            $path = PIMCORE_TEMPORARY_DIRECTORY . "/video-image-cache/video_" . $this->asset->getId() . "__thumbnail_" .  $timeOffset . ".png";

            if (!is_dir(dirname($path))) {
                File::mkdir(dirname($path));
            }

            if (!is_file($path)) {
                $lockKey = "video_image_thumbnail_" . $this->asset->getId() . "_" . $timeOffset;
                Model\Tool\Lock::acquire($lockKey);

                // after we got the lock, check again if the image exists in the meantime - if not - generate it
                if (!is_file($path)) {
                    $converter->saveImage($path, $timeOffset);
                }

                Model\Tool\Lock::release($lockKey);
            }

            if ($this->getConfig()) {
                $this->getConfig()->setFilenameSuffix("time-" . $timeOffset);

                try {
                    $path = Image\Thumbnail\Processor::process($this->asset, $this->getConfig(), $path, false, true);
                } catch (\Exception $e) {
                    \Logger::error("Couldn't create image-thumbnail of video " . $this->asset->getRealFullPath());
                    \Logger::error($e);
                    $path = $errorImage;
                }
            }

            $this->filesystemPath = $path;
        }
    }

    /**
     *
     */
    public function reset()
    {
        $this->filesystemPath = null;
        $this->width = null;
        $this->height = null;
        $this->realHeight = null;
        $this->realWidth = null;
    }

    /**
     * Get the public path to the thumbnail image.
     * This method is here for backwards compatility.
     * Up to Pimcore 1.4.8 a thumbnail was returned as a path to an image.
     * @return string Public path to thumbnail image.
    */
    public function __toString()
    {
        return $this->getPath();
    }

    /**
     * @return int Width of the generated thumbnail image.
    */
    public function getWidth()
    {
        if (!$this->width) {
            $this->getDimensions();
        }
        return $this->width;
    }

    /**
     * Get the width of the generated thumbnail image in pixels.
     * @return int Height of the generated thumbnail image.
    */
    public function getHeight()
    {
        if (!$this->height) {
            $this->getDimensions();
        }
        return $this->height;
    }

    /**
     * @return int real Width of the generated thumbnail image. (when using high resolution option)
    */
    public function getRealWidth()
    {
        if (!$this->realWidth) {
            $this->getDimensions();
        }
        return $this->realWidth;
    }

    /**
     * Get the real width of the generated thumbnail image in pixels. (when using high resolution option)
     * @return int Height of the generated thumbnail image.
    */
    public function getRealHeight()
    {
        if (!$this->realHeight) {
            $this->getDimensions();
        }
        return $this->realHeight;
    }

    /**
     * @return array
     */
    public function getDimensions()
    {
        if (!$this->width || !$this->height) {
            $config = $this->getConfig();
            $dimensions = [];

            // generate the thumbnail and get dimensions from the thumbnail file
            $info = @getimagesize($this->getFileSystemPath());
            if ($info) {
                $dimensions = [
                    "width" => $info[0],
                    "height" => $info[1]
                ];
            }

            $this->width = $dimensions["width"];
            $this->height = $dimensions["height"];

            // the following is only relevant if using high-res option (retina, ...)
            $this->realHeight = $this->height;
            $this->realWidth = $this->width;

            if ($config && $config->getHighResolution() && $config->getHighResolution() > 1) {
                $this->realWidth = floor($this->width * $config->getHighResolution());
                $this->realHeight = floor($this->height * $config->getHighResolution());
            }
        }

        return [
            "width" => $this->width,
            "height" => $this->height
        ];
    }

    /**
     * @return \Pimcore\Model\Asset\Image The original image from which this thumbnail is generated.
    */
    public function getAsset()
    {
        return $this->asset;
    }

    /**
     * Get thumbnail image configuration.
     * @return Image\Thumbnail\Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param $selector
     * @return bool|static
     */
    protected function createConfig($selector)
    {
        $config = Image\Thumbnail\Config::getByAutoDetect($selector);
        if ($config) {
            $format = strtolower($config->getFormat());
            if ($format == "source") {
                $config->setFormat("PNG");
            }
        }

        return $config;
    }
}