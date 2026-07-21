<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Upgrade\UpgradeRunner;

/**
 * مزود خدمات البائع – يسجل جميع الشورت كودات، الهوكات، وتحميل الأصول
 * ✅ النسخة النهائية المعتمدة مع جميع الإصلاحات والتحسينات
 * ✅ إضافة register_nonce لدعم تسجيل البائع
 * 
 * @package VMP\Providers
 * @since 1.0.0
 */
class VendorServiceProvider extends ServiceProvider
{
    /**
     * نقطة الدخول الرئيسية للمزود
     * يتم استدعاؤها من Kernel::boot()
     * 
     * @return void
     */
    public function boot(): void
    {
        // ─── 1. إضافة vendor_store إلى query_vars ───
        // (المصدر الوحيد — حُذفت النسخة المكررة من vendor-marketplace.php)
        add_filter('query_vars', function (array $vars): array {
            if (!in_array('vendor_store', $vars, true)) {
                $vars[] = 'vendor_store';
            }
            return $vars;
        });

        // ─── 2. استخدام قالب الصفحة العادي لعرض المتجر ───
        add_filter('template_include', function ($template) {
            if (get_query_var('vendor_store')) {
                $new_template = locate_template(['page.php', 'singular.php', 'index.php']);
                return $new_template ?: $template;
            }
            return $template;
        }, 99);

        // ─── 3. منع إعادة التوجيه غير المرغوب فيه ───
        // (المصدر الوحيد — حُذفت النسخة المكررة من vendor-marketplace.php)
        add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
            if (get_query_var('vendor_store')) {
                return false;
            }
            return $redirect_url;
        }, 10, 2);

        // ─── 4. استبدال محتوى صفحة vendor-store بالشورت كود مع slug ───
        // (نُقل من vendor-marketplace.php ليكون هنا المصدر الوحيد)
        add_filter('the_content', function ($content) {
            if (is_page('vendor-store') && get_query_var('vendor_store')) {
                $slug = sanitize_text_field(get_query_var('vendor_store'));
                return do_shortcode('[vmp_vendor_store slug="' . esc_attr($slug) . '"]');
            }
            return $content;
        }, 10, 1);

        // ─── 5. تسجيل الشورت كودات (تعمل دائماً) ───
        $this->registerShortcodes();

        // ─── 6. التحقق من WooCommerce ───
        $woocommerceActive = $this->container->has('woocommerce.active')
            && (bool) $this->container->make('woocommerce.active');

        // ─── 6. تسجيل الهوكات المعتمدة على WooCommerce ───
        if ($woocommerceActive) {
            $this->registerWooCommerceHooks();
        }

        // ─── 7. تحميل الأصول (CSS/JS) ───
        $this->registerAssets();

        // ─── 8. عرض اسم البائع في صفحات WooCommerce العامة ───
        $this->registerVendorNameInWooCommerce();

        // ─── 9. Run upgrade runner to apply versioned migrations safely ───
        try {
            if (class_exists(UpgradeRunner::class)) {
                (new UpgradeRunner())->run();
            }
        } catch (\Throwable $e) {
            if ($this->container->has('logger')) {
                $this->make('logger')->error('Upgrade runner failed', ['error' => $e->getMessage()]);
            } else {
                error_log('[VMP] Upgrade runner failed: ' . $e->getMessage());
            }
        }
    }

    // ... بقية الدوال بدون تغيير (التضمين كما كان سابقًا)

    private function renderTemplate(string $template, string $page = '', array $vars = []): string
    {
        // تعيين العلم بأن هذه صفحة VMP (لـ Page Builders و has_shortcode)
        $GLOBALS['vmp_is_active'] = true;

        // تخزين الصفحة الحالية للاستخدام في registerAssets (موثوق مع جميع الـ permalinks)
        if ($page) {
            $GLOBALS['vmp_current_page'] = $page;
        }

        // استخراج المتغيرات إلى النطاق المحلي (آمن لأن المصدر موثوق)
        if (!empty($vars)) {
            extract($vars); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }

        ob_start();
        $file = VMP_PLUGIN_DIR . 'public/templates/' . $template;
        if (file_exists($file)) {
            require_once $file;
        } else {
            // رسالة خطأ في حال عدم وجود القالب (للتطوير)
            echo '<div class="vmp-notice vmp-notice-error">';
            echo sprintf(__('قالب %s غير موجود.', 'vmp'), esc_html($template));
            echo '</div>';
        }
        return ob_get_clean();
    }

    // rest of file unchanged: registerShortcodes, registerWooCommerceHooks, registerAssets, registerVendorNameInWooCommerce
}
