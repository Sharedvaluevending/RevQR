<?php
/**
 * User Behavior Tracker Include
 * Include this file to add behavior tracking to any page
 */

// Only include tracking for logged-in users to respect privacy
if (is_logged_in()): ?>
<script src="<?php echo APP_URL; ?>/assets/js/user-behavior-tracker.js?v=<?php echo time(); ?>"></script>
<script>
// Initialize tracking with user context
if (window.UserBehaviorTracker) {
    window.userBehaviorTracker = new UserBehaviorTracker();
    
    // Add user context if available
    <?php if (function_exists('get_user_id') && get_user_id()): ?>
    window.userBehaviorTracker.userId = <?php echo get_user_id(); ?>;
    <?php endif; ?>
    
    <?php if (function_exists('get_user_role') && get_user_role()): ?>
    window.userBehaviorTracker.userRole = '<?php echo get_user_role(); ?>';
    <?php endif; ?>
    
    // Track page-specific context
    window.userBehaviorTracker.pageContext = {
        section: '<?php echo basename(dirname($_SERVER['PHP_SELF'])); ?>',
        page: '<?php echo basename($_SERVER['PHP_SELF']); ?>',
        timestamp: Date.now()
    };
}
</script>
<?php endif; ?> 