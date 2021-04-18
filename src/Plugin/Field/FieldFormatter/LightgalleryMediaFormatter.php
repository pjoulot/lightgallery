<?php

namespace Drupal\lightgallery\Plugin\Field\FieldFormatter;

use Drupal\file\Entity\File;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\lightgallery\Manager\LightgalleryManager;
use Drupal\lightgallery\Optionset\LightgalleryOptionset;

use Drupal\lightgallery\Field\FieldInterface;
use Drupal\lightgallery\Field\FieldLightgalleryImageStyle;
use Drupal\lightgallery\Field\FieldThumbImageStyle;
use Drupal\lightgallery\Field\FieldTitleSource;
use Drupal\lightgallery\Field\FieldUseThumbs;
use Drupal\lightgallery\Group\GroupInterface;
use Drupal\lightgallery\Group\GroupsEnum;

/**
 * @FieldFormatter(
 *   id = "lightgallery_media",
 *   label = @Translation("Lightgallery Media"),
 *   field_types = {
 *     "entity_reference",
 *   }
 * )
 */
class LightgalleryMediaFormatter extends LightgalleryFormatter {

      /**
   * {@inheritdoc}
   *
   * This has to be overridden because FileFormatterBase expects $item to be
   * of type \Drupal\file\Plugin\Field\FieldType\FileItem and calls
   * isDisplayed() which is not in FieldItemInterface.
   *
   * @see \Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter::needsEntityLoad()
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   *
   * This extracts the file entities (thumbnail from the media entities) and
   * then updates FieldItemList $items with files instead of media so it can
   * hand building the renderable array back to parent::viewElements().
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $new_items = [];
    $media_items = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return [];
    }

    // Init lightgallery image style field.
    $lightgallery_image_style_field = new FieldLightgalleryImageStyle();
    // Fetch lightgallery image style.
    $lightgallery_image_style = $this->settings[$lightgallery_image_style_field->getGroup()
      ->getName()][$lightgallery_image_style_field->getName()];
    // Init thumb image style field.
    $thumb_image_style_field = new FieldThumbImageStyle();
    // Fetch thumb image style.
    $thumb_image_style = $this->settings[$thumb_image_style_field->getGroup()
      ->getName()][$thumb_image_style_field->getName()];
    // Init title source field.
    $title_source_field = new FieldTitleSource();
    $title_source = $this->settings[$title_source_field->getGroup()
      ->getName()][$title_source_field->getName()];

    /** @var \Drupal\media\MediaInterface $media */
    foreach ($media_items as $delta => $media) {
      $item_detail = array();

      if ($media->hasField('thumbnail')) {
        $thumbUri = $media->get('thumbnail')->first()->entity->getFileUri();
      }

      if ($media->bundle() === 'video') {
        $field = $media->get('field_media_video_file');
        $ent = $media->field_media_video_file->entity;
        $slide_url = $ent->createFileUrl();
      }
      else if ($media->bundle() === 'remote_video') {
        $field = $media->get('field_media_oembed_video');
        $slide_url = $field->value;
      }
      else if ($media->bundle() === 'image') {
        $field = $media->get('field_media_image');
        $ent = $media->field_media_image->entity;
        $thumbUri = $ent->getFileUri();
        if (!empty($lightgallery_image_style)) {
          $slide_url = ImageStyle::load($lightgallery_image_style)
            ->buildUrl($ent->getFileUri());
        }
        else {
          $slide_url = $ent->createFileUrl();
        }
      }
      else {
        continue;
      }

      // Generate the thumbnail.
      if (!empty($thumb_image_style)) {
        $item_detail['thumb'] = ImageStyle::load($thumb_image_style)
          ->buildUrl($thumbUri);
      }
      else {
        $item_detail['thumb'] = file_create_url($thumbUri);
      }

      $item_detail['alt'] = $field->alt;
      $item_detail['title'] = $field->title;
      $item_detail['width'] = $field->width;
      $item_detail['height'] = $field->height;
      $item_detail['slide'] = $slide_url;
      $item_detail['type'] = $media->bundle();

      $new_items[] = $item_detail;
    }

     // Flatten settings array.
     $options = LightgalleryManager::flattenArray($this->settings);
     // Set unique id, so that multiple instances on one page can be created.
     $unique_id = uniqid();
     // Load libraries.
     $lightgallery_optionset = new LightgalleryOptionset($options);
     $lightgallery_manager = new LightgalleryManager($lightgallery_optionset);
     // Build render array.
     $content = array(
       '#theme' => 'lightgallery',
       '#items' => $new_items,
       '#id' => $unique_id,
       '#attached' => $lightgallery_manager->loadLibraries($unique_id),
     );

     return $content;
  }
}
