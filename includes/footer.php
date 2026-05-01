            </div><!-- .page-content -->
        </main>
    </div><!-- .main-layout -->
    
    <?php if (isset($needChart) && $needChart): ?>
    <script src="/assets/js/chart.umd.min.js"></script>
    <?php endif; ?>
    <script src="<?php echo getVersionedAsset('/assets/js/main.js'); ?>"></script>
    <?php if (isset($extraJs)): ?>
        <?php echo $extraJs; ?>
    <?php endif; ?>
</body>
</html>
