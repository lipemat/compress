<?php
/**
 * @var string $screen_icon
 * @var string $title
 * @var string $content
 */
?>
<div class="wrap">
		<?php screen_icon(empty($screen_icon)?'options-general':$screen_icon); ?>
		<h2><?php echo $title; ?></h2>
		<?php echo $content; ?>
</div>
