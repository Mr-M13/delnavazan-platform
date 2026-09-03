<?php
namespace Delnavazan\Platform\Admin\Controller;

final class Menu {
    public static function register(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function menu(): void {
        add_menu_page('Delnavazan', 'Delnavazan', 'dzn_view_diagnostics', 'dzn-platform', [ScreenController::class, 'status'], 'dashicons-networking', 58);
        add_submenu_page('dzn-platform', 'Core Status', 'Core Status', 'dzn_view_diagnostics', 'dzn-platform', [ScreenController::class, 'status']);
        foreach ([
            'Teachers' => ['teacher', 'dzn_manage_teachers'],
            'Students' => ['student', 'dzn_manage_students'],
            'Instruments' => ['instrument', 'dzn_manage_courses'],
            'Courses' => ['course', 'dzn_manage_courses'],
            'Enrolments' => ['enrolment', 'dzn_manage_enrolments'],
            'Terms' => ['term', 'dzn_manage_terms'],
            'Lessons' => ['lesson', 'dzn_manage_lessons'],
            'Exceptions' => ['exception', 'dzn_manage_exceptions'],
        ] as $label => [$screen, $capability]) {
            add_submenu_page('dzn-platform', $label, $label, $capability, 'dzn-' . $screen, static function () use ($screen): void {
                ScreenController::screen($screen);
            });
        }
    }
}
