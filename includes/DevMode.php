<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

class DevMode
{
    public function onLoaded()
    {
        add_action('show_user_profile', [$this, 'ssoUserAttributesFields']);
        add_action('edit_user_profile', [$this, 'ssoUserAttributesFields']);

        add_action('personal_options_update', [$this, 'saveSSOUserAttributes']);
        add_action('edit_user_profile_update', [$this, 'saveSSOUserAttributes']);
    }

    public function ssoUserAttributesFields(object $user)
    {
        $eduPersonAffiliation = $this->joinArrayValue((array) get_the_author_meta('edu_person_affiliation', $user->ID));
        $eduPersonEntitlement = $this->joinArrayValue((array) get_the_author_meta('edu_person_entitlement', $user->ID));
        ?>
        <h3><?php _e('SSO User Attributes', 'cms-dev'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="person-affiliation"><?php _e('Person affiliation', 'cms-dev'); ?></label></th>
                <td>
                    <input type="text" name="edu_person_affiliation" id="person-affiliation" value="<?php echo esc_attr($eduPersonAffiliation); ?>" class="regular-text" /><br />
                    <span class="description"><?php _e('Separate with commas or the Enter key.', 'cms-dev'); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="person-entitlement"><?php _e('Person entitlement', 'cms-dev'); ?></label></th>
                <td>
                    <input type="text" name="edu_person_entitlement" id="person-entitlement" value="<?php echo esc_attr($eduPersonEntitlement); ?>" class="regular-text" /><br />
                    <span class="description"><?php _e('Separate with commas or the Enter key.', 'cms-dev'); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }

    public function saveSSOUserAttributes(int $userId)
    {
        if (!current_user_can('edit_user', $userId)) {
            return false;
        }

        $eduPersonAffiliation = isset($_POST['edu_person_affiliation']) ? $this->splitTextInput($_POST['edu_person_affiliation']) : '';
        $eduPersonEntitlement = isset($_POST['edu_person_entitlement']) ? $this->splitTextInput($_POST['edu_person_entitlement']) : '';

        update_user_meta($userId, 'edu_person_affiliation', $eduPersonAffiliation);
        update_user_meta($userId, 'edu_person_entitlement', $eduPersonEntitlement);
    }

    protected function joinArrayValue(array $array, string $glue = ','): string
    {
        return implode($glue, $array);
    }

    protected function splitTextInput(string $string, string $delimiter = ','): array
    {
        $split = explode($delimiter, $string);
        return array_unique(array_map(function ($item) {
            return trim($item, ' ');
        }, $split));
    }
}
