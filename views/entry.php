<?php

class WPCF7_Entries_View
{
    public function parse_repeater_fields($field, $base_name = '', &$name = array(), &$depth = 0)
    {
        foreach ($field as $key => $_value) {

            $obase_name = $base_name;
            $base_name .= "[$key]";

            if (is_array($_value) && $depth < 1) {
                $depth++;
                $this->parse_repeater_fields($_value, $base_name, $name, $depth);
            } else {
                $field_value = $_value;
                $name[$base_name] = array($key, $_value);
            }

            $base_name = $obase_name;
        }
        return $name;
    }
    public function print_fields($form, $type, $field, $field_name)
    {
        $name = substr($field_name, 0, 1) == '[' ? 'fields' . $field_name : 'fields[' . $field_name . ']';
        switch ($type['basetype']) {
            case 'textarea': ?>
                <textarea name="<?php echo $name; ?>"><?php echo $field; ?></textarea>
            <?php
                break;
            case 'select':
                $multiple = false;
                $include_blank  = false;
                foreach ($type['options'] as $option) {
                    if ($option == 'multiple') {
                        $multiple = true;
                    }
                    if ($option == 'include_blank') {
                        $include_blank = true;
                    }
                }
            ?>
                <select <?php echo $multiple ? 'multiple' : ''; ?> name="<?php echo $name; ?><?php echo $multiple ? '[]' : ''; ?>">
                    <?php
                    if ($include_blank) {
                    ?>
                        <option value="">--</option>
                    <?php
                    }
                    $field_array = array_combine($type['values'], $type['labels']);
                    foreach ($field_array as $key => $_value) {
                        $name = $_value;
                        $selected = false;
                        if (is_array($field)) {
                            $name = $name . '[]';
                            $selected = in_array($key, $field);
                        } else {
                            $selected = $key == $field;
                        }
                    ?>
                        <option <?php echo $selected ? 'selected="selected"' : ''; ?> value="<?php echo $key; ?>"><?php echo $_value; ?></option>
                    <?php
                    } ?>

                </select>
                <?php
                break;
            case 'acceptance':
            case 'checkbox':
            case 'radio':
                $field_array = array_combine($type['values'], $type['labels']);
                $field_name = $name;
                foreach ($field_array as $key => $label) {
                    $selected = false;
                    if (is_array($field)) {
                        $field_name = $name . '[]';
                        $selected = in_array($key, $field);
                    } else {
                        $selected = $key == $field;
                    }
                ?>
                    <label><input type="<?php echo $type['basetype']; ?>" <?php echo $selected ? 'checked="checked"' : ''; ?> name="<?php echo $field_name; ?>" value="<?php echo $key; ?>" /> <?php echo $label; ?></label>
                <?php
                }
                break;
            case 'file': ?>
                <a href="<?php echo $field; ?>">Download File</a>
                <?php
                break;
            case 'repeater':
                $repeater_name = "[$field_name]";
                foreach ($field as $repeater) {
                ?>
                    <div class="wpcf7-entry-meta-repeater-fields" style="padding:10px;border:1px solid #eee;margin-bottom:10px">
                        <?php
                        $name_values = $this->parse_repeater_fields($repeater);
                        foreach ($name_values as $_name => $_value) {
                            foreach ($type->subtags  as $tag) {
                                if ($tag->name == $_value[0]) {
                        ?>
                                    <div class="wpcf7-entry-meta-repeater-field"><label><?php echo $tag->name; ?></label>
                                        <div class="inner"><?php $this->print_fields($form, $tag, $_value[1], $repeater_name . $_name); ?></div>
                                    </div>
                        <?php
                                    break;
                                }
                            }
                        }

                        ?>
                    </div>
                <?php
                }
                break;
            default:
                ?>
                <input type="text" name="<?php echo $name; ?>" value="<?php echo $field; ?>" />
            <?php
                break;
        }
    }
    public function metabox($post_id)
    {
        $form_id = get_post_meta($post_id, 'form_id',  true);
        $form = WPCF7_ContactForm::get_instance($form_id);
        $form_fields = $form->scan_form_tags();

        $fields = get_post_meta($post_id, 'wpcf7-fields', true);
        if (!empty($fields)) {
            ?>
            <table class="widefat wpcf7-entry-meta-table">
                <?php
                foreach ($fields as $name) :
                    $field = $field_values[$name] ?? get_post_meta($post_id, 'wpcf7-field-' . $name, true);
                    $type = null;
                    foreach ($form_fields as $form_field) {
                        if ($form_field['name'] == $name) {
                            $type = $form_field;
                            break;
                        }
                    }
                    if ($type == null) {
                        $manager = WPCF7_FormTagsManager::get_instance();
                        $properties = $form->get_properties();
                        $index = -1;
                        foreach ($form_fields as $form_field) {
                            if ($form_field['basetype'] == 'repeater') {
                                foreach ($form_field['options'] as $v) {
                                    if (strpos($v, 'index:') !== false) {
                                        $index = intval(str_replace('index:', '', $v));
                                        break;
                                    }
                                }
                                if ($index >= 0 && $name == 'repeater-' . $index) {
                                    if (isset($properties['repeater'][$index]['text'])) {
                                        $form_field->subtags = $manager->scan($properties['repeater'][$index]['text']);
                                    }
                                    $type = $form_field;
                                    break;
                                }
                            }
                        }
                    }
                ?>
                    <tr>
                        <th><?php
                            echo $name;
                            ?></th>
                        <td>
                            <?php echo $this->print_fields($form, $type, $field, $name); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
<?php
        }
    }
}
