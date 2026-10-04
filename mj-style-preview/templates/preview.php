<?php
namespace MJStylePreview;
defined('ABSPATH') || exit;
?>
<section class="mjsp" data-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" aria-labelledby="<?php echo esc_attr($id); ?>title">
 <header class="mjsp__header"><p class="mjsp__eyebrow">MJ HAIR ARTIST / THE CONSULTATION STUDIO</p><h2 id="<?php echo esc_attr($id); ?>title">MJ Style Preview</h2><p>See your next look before you make the change.</p></header>
 <form class="mjsp__form">
  <div class="mjsp__step"><span class="mjsp__number" aria-hidden="true">01</span><div><label for="<?php echo esc_attr($id); ?>photo">Upload a photo</label><p id="<?php echo esc_attr($id); ?>photo-help">Face and hair clearly visible. Good lighting. Avoid hats when possible.</p>
  <input id="<?php echo esc_attr($id); ?>photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="<?php echo esc_attr($id); ?>photo-help"><p class="mjsp__small">JPG, PNG or WebP · up to <?php echo esc_html(settings()['upload']); ?> MB and 16 megapixels. Export HEIC photos as JPG.</p><img class="mjsp__selected" alt="Your selected photo" hidden></div></div>
  <div class="mjsp__step"><span class="mjsp__number" aria-hidden="true">02</span><div><label for="<?php echo esc_attr($id); ?>hair">What would you like to try?</label><textarea id="<?php echo esc_attr($id); ?>hair" name="hairstyle" rows="4" maxlength="1200" required placeholder="Long layers, big loose curls, warm blonde highlights with darker roots…" aria-describedby="<?php echo esc_attr($id); ?>hair-help"></textarea><p id="<?php echo esc_attr($id); ?>hair-help" class="mjsp__small">Describe hair length, cut, texture and color in English. This first edition recognizes common hair descriptions; other details may be ignored. Describe what you want, rather than what to avoid.</p></div></div>
  <div class="mjsp__consent"><input id="<?php echo esc_attr($id); ?>consent" type="checkbox" name="consent" required value="1"><label for="<?php echo esc_attr($id); ?>consent">I confirm that I have permission to use this photo and understand that it will be processed by AI to create a hairstyle preview.</label></div>
  <p class="mjsp__small">Your photo is sent to OpenAI after you submit. We don’t add it to a media library or gallery. Temporary server copies are deleted after processing; abandoned copies are cleaned up periodically. OpenAI’s retention policies apply.</p>
  <button class="mjsp__primary" type="submit">Preview my look <span aria-hidden="true">↗</span></button>
 </form>
 <p class="mjsp__status" role="status" aria-live="polite" tabindex="-1"></p>
 <div class="mjsp__result" hidden tabindex="-1"><p class="mjsp__eyebrow">A NEW POSSIBILITY</p><h3>Your style preview</h3><div class="mjsp__comparison"><figure><figcaption>Original</figcaption><img class="mjsp__original" alt="Your original photograph"></figure><figure><figcaption>Preview</figcaption><img class="mjsp__generated" alt="AI-generated hairstyle inspiration"></figure></div><p class="mjsp__applied mjsp__small"></p><p class="mjsp__small">Previews clear automatically after <?php echo esc_html(settings()['lifetime']); ?> minutes. AI may change details beyond your hair.</p><button class="mjsp__again" type="button">Try another look</button>
 <?php $booking=apply_filters('mj_style_preview_booking_url',''); if (is_string($booking) && preg_match('#^https://#i',$booking)) : ?><a class="mjsp__booking" href="<?php echo esc_url($booking); ?>">Book with MJ ↗</a><?php endif; ?>
 </div>
 <footer class="mjsp__disclaimer">AI style previews are for inspiration only. Hair condition, existing color, previous chemical treatments, texture, and other factors affect what can safely and realistically be achieved. MJ can help determine what’s appropriate for your hair.</footer>
</section>
