  </main>
  <footer class="site-footer">
    <p>&copy; <?= date('Y'); ?> Stadium Pass System. All rights reserved.</p>
  </footer>
<?php if (!empty($body_class)): ?>
  <script src="<?= $root_path ?>assets/vendor/three.min.js" defer></script>
  <script src="<?= $root_path ?>assets/vendor/gsap.min.js" defer></script>
  <script src="<?= $root_path ?>assets/vendor/ScrollTrigger.min.js" defer></script>
  <script src="<?= $root_path ?>assets/home.js" defer></script>
<?php if (!empty($show_intro)): ?>
  <script src="<?= $root_path ?>assets/intro.js" defer></script>
<?php endif; ?>
<?php if (!empty($show_video)): ?>
  <script src="<?= $root_path ?>assets/scrollvideo.js" defer></script>
<?php endif; ?>
<?php endif; ?>
</body>
</html>
