{{--
    Shared recursive option builders for the dashboard category/choice
    selects (M-24). Previously copy-pasted into main_categories,
    choices and products create/edit views under three different names
    (buildCategoryOptions / buildchoiceOptions / getSubCategoriesEdit).
    Include this partial BEFORE the first use in the view.
--}}
@php
    if (! function_exists('buildCategoryOptions')) {
        function buildCategoryOptions($categories, $parent_id = null, $path = '', $selectedId = null)
        {
            $options = '';
            foreach ($categories as $category) {
                if ($category->parent_id == $parent_id) {
                    $fullPath = $path ? $path . ' / ' . $category->name : $category->name;
                    $selected = $selectedId == $category->id ? 'selected' : '';
                    $options .= '<option value="' . $category->id . '" ' . $selected . '>' . e($fullPath) . '</option>';
                    $options .= buildCategoryOptions($categories, $category->id, $fullPath, $selectedId);
                }
            }
            return $options;
        }
    }

    if (! function_exists('getSubCategories')) {
        function getSubCategories($subCategories, $prefix = '', $selectedId = null)
        {
            $html = '';
            foreach ($subCategories as $category) {
                $sel = $selectedId == $category->id ? 'selected' : '';
                $html .= '<option value="' . $category->id . '" ' . $sel . '>' . e($prefix . $category->name) . '</option>';
                if ($category->children->isNotEmpty()) {
                    $html .= getSubCategories($category->children, $prefix . $category->name . ' / ', $selectedId);
                }
            }
            return $html;
        }
    }
@endphp
