<?php
/**
 * class to provide simple static functions for image handling
 */
class CRM_Utils_Image {
  function __construct($source, $destination, $quality = 90, $replace = FALSE) {
    if (!is_file($source) && !is_uploaded_file($source)) {
      return FALSE;
    }
    if (is_file($destination) && $source !== $destination) {
      if (!$replace) {
        $destination = CRM_Utils_File::existsRename($destination);
      }
    }
    if (empty($quality)) {
      $quality = 90;
    }
    $this->source = $source;
    $this->destination = $destination;

    $data = @getimagesize($this->source);
    if (isset($data) && is_array($data)) {
      $extensions = array('1' => 'gif', '2' => 'jpg', '3' => 'png');
      $extension = isset($extensions[$data[2]]) ? $extensions[$data[2]] : '';
      $aspect = $data[1] / $data[0];
      $this->info = array(
        'width' => $data[0],
        'height' => $data[1],
        'extension' => $extension,
        'mime_type' => $data['mime'],
        'aspect' => $aspect
      );
      $this->convert = array(
        'quality' => $quality,
      );
    }
  }

  function getConvertDimensions($width, $height, $upscale) {
    $aspect = $this->info['aspect'];
    $widthCal = $width;
    $heightCal = $height;

    // get dimension
    if (($width && !$height) || ($width && $height && $aspect < $height / $width)) {
      $heightCal = (int) round($width * $aspect);
    }
    else {
      $widthCal = (int) round($height / $aspect);
    }
    if (!$upscale) {
      if ($this->info['width'] >= $width && $this->info['height'] >= $height) {
        $width = (int) round($widthCal);
        $height = (int) round($heightCal);
      }
      else {
        $this->convert['skip'] = TRUE;
        $width = $this->info['width'];
        $height = $this->info['height'];
      }
    }
    else {
      $width = (int) round($widthCal);
      $height = (int) round($heightCal);
    }
    return array($width, $height);
  }

  function gdCreateResource() {
    // create image gd resource
    $extension = str_replace('jpg', 'jpeg', $this->info['extension']);
    $function = 'imagecreatefrom' . $extension;
    if (function_exists($function) && $this->resource = $function($this->source)) {
      if (!imageistruecolor($this->resource)) {
        // Convert indexed images to truecolor, copying the image to a new
        // truecolor resource, so that filters work correctly and don't result
        // in unnecessary dither.
        $this->gdCreateTmp($this->info['width'], $this->info['height']);
        if ($this->tmp) {
          imagecopy($this->tmp, $this->resource, 0, 0, 0, 0, imagesx($this-tmp), imagesy($this->tmp));
          imagedestroy($this->resource);
          $this->resource = $this->tmp;
        }
      }
    }
  }

  function gdCreateTmp($width, $height) {
    unset($this->tmp);
		$res = @imagecreatetruecolor($width, $height);

		if ($this->info['extension'] == 'gif') {
			// Find out if a transparent color is set, will return -1 if no
			// transparent color has been defined in the image.
			$transparent = imagecolortransparent($this->resource);

			if ($transparent >= 0) {
				// Find out the number of colors in the image palette. It will be 0 for
				// truecolor images.
				$palette_size = imagecolorstotal($this->resource);
				if ($palette_size == 0 || $transparent < $palette_size) {
					// Set the transparent color in the new resource, either if it is a
					// truecolor image or if the transparent color is part of the palette.
					// Since the index of the transparency color is a property of the
					// image rather than of the palette, it is possible that an image
					// could be created with this index set outside the palette size (see
					// http://stackoverflow.com/a/3898007).
					$transparent_color = imagecolorsforindex($this->resource, $transparent);
					$transparent = imagecolorallocate($res, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);

					// Flood with our new transparent color.
					imagefill($res, 0, 0, $transparent);
					imagecolortransparent($res, $transparent);
				}
				else {
					imagefill($res, 0, 0, imagecolorallocate($res, 255, 255, 255));
				}
			}
		}
		elseif ($this->info['extension'] == 'png') {
			imagealphablending($res, FALSE);
			$transparency = imagecolorallocatealpha($res, 0, 0, 0, 127);
			imagefill($res, 0, 0, $transparency);
			imagealphablending($res, TRUE);
			imagesavealpha($res, TRUE);
		}
		else {
			imagefill($res, 0, 0, imagecolorallocate($res, 255, 255, 255));
		}
    $this->tmp = $res;
  }

  function save() {
    $extension = str_replace('jpg', 'jpeg', $this->info['extension']);
    $function = 'image' . $extension;
    $tmpDir = CRM_Utils_System::cmsDir('temp');
    $tempName = tempnam($tmpDir, 'crmgd_');

    if ($this->convert['skip']) {
      @copy($this->source, $this->destination);
      return $success;
    }
    elseif (function_exists($function)) {
      if ($extension == 'jpeg') {
        $success = $function($this->resource, $tempName, $this->convert['quality']); 
      }
      else {
				// Always save PNG images with full transparency.
				if ($extension == 'png') {
					imagealphablending($this->resource, FALSE);
					imagesavealpha($this->resource, TRUE);
				}
				$success = $function($this->resource, $tempName);
      }
    }
      
    if ($success) {
      @copy($tempName, $this->destination);
      @unlink($tempName);
      return $success;
    }
    return FALSE;
  }

  function resize($width, $height) {
    $this->convert['width'] = (int) round($width);
    $this->convert['height'] = (int) round($height);

    // create original image and temp canvas
    $this->gdCreateResource();
    $this->gdCreateTmp($width, $height);

    $result = imagecopyresampled(
      $this->tmp,
      $this->resource,
      0,
      0,
      0,
      0,
      $this->convert['width'],
      $this->convert['height'],
      $this->info['width'],
      $this->info['height']
    );
    if ($result) {
      imagedestroy($this->resource);
      $this->resource = $this->tmp;
      $this->info['width'] = $width;
      $this->info['height'] = $height;
      return TRUE;
    }
    return FALSE;
  }

  function sharpen($matrix = array()) {
    if (empty($matrix)) {
      $matrix = array( 
        array(0, -2, 0),
        array(-2, 11, -2),
        array(0, -2, 0),
      );
    }
    $divisor = array_sum(array_map('array_sum', $matrix));
    $offset = 0;
    imageconvolution($this->resource, $matrix, $divisor, $offset);
  }

  function crop($x, $y, $width, $height) {
		$width = (int) round($width);
		$height = (int) round($height);
    $this->convert['width'] = $width;
    $this->convert['height'] = $height;
    $this->gdCreateTmp($width, $height);
    $result = imagecopyresampled(
      $this->tmp,
      $this->resource,
      0,
      0,
      $x,
      $y,
      $width,
      $height,
      $width,
      $height
    );
    if ($result) {
      imagedestroy($this->resource);
      $this->resource = $this->tmp;
      $this->info['width'] = $width;
      $this->info['height'] = $height;
      return TRUE;
    }
    return FALSE;
  }

  function scale($width, $height, $upscale = FALSE) {
    list($width, $height) = $this->getConvertDimensions($width, $height, $upscale);
    if ($this->convert['skip']) {
      return $this->save();
    }
    elseif ($this->resize($width, $height)) {
      $this->sharpen();
      return $this->save();
    }
    else {
      return FALSE;
    }
  }

  function scaleCrop($width, $height, $upscale = FALSE) {
    $scale = max($width / $this->info['width'], $height / $this->info['height']);
    $x = ($this->info['width'] * $scale - $width) / 2;
    $y = ($this->info['height'] * $scale - $height) / 2;
    if ($this->resize($this->info['width'] * $scale, $this->info['height'] * $scale)) {
      if ($this->crop($x, $y, $width, $height)) {
        $this->sharpen();
        return $this->save();
      }
    }
    return FALSE;
  }

  /**
   * function to return proportional height and width and image url for passing modal
   *
   * @return Array thumb dimension of image
   */
  public static function getImageVars($file) {
    list($imageWidth, $imageHeight) = @getimagesize($file);
    $thumbWidth = 125;
    if ($imageWidth && $imageHeight) {
      $imageRatio = $imageWidth / $imageHeight;
    }
    else {
      $imageRatio = 1;
    }
    if ($imageRatio > 1) {
      $imageThumbWidth = $thumbWidth;
      $imageThumbHeight = round($thumbWidth / $imageRatio);
    }
    else {
      $imageThumbHeight = $thumbWidth;
      $imageThumbWidth = $thumbWidth * $imageRatio;
    }
    return array(
      'url' => $file,
      'width' => $imageWidth,
      'height' => $imageHeight,
      'thumb' => array(
        'width' => $imageThumbWidth,
        'height' => $imageThumbHeight,
      ),
    );
    return FALSE;
  }

  /**
   * function to return proportional height and width of the image
   *
   * @return Array thumb dimension of image
   */
  public static function getImageModal($vars) {
    $template = CRM_Core_Smarty::singleton();
    $template->assign('modalImage', $vars);

    return $template->fetch('CRM/common/modal.tpl');
  }

  function __destruct() {
    imagedestroy($this->resource);
    imagedestroy($this->tmp);
  }
}
