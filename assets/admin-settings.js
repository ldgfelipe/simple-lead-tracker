jQuery(document).ready(function($) {
    let data = JSON.parse($('#slt_hierarchical_tracking_input').val() || "[]");

    function render() {
        const container = $('#slt-pages-container').empty();
        data.forEach((page, pIdx) => {
            const pageHtml = $(`
                <div class="slt-page-box" data-pidx="${pIdx}">
                    <div class="slt-page-header">
                        <strong>URL:</strong> <input type="text" value="${page.url || ''}" class="slt-page-url large-text" placeholder="/url-ejemplo o *">
                        <span class="slt-delete slt-remove-page">Eliminar Página</span>
                    </div>
                    <div class="rules-sub-container"></div>
                    <button type="button" class="button button-small slt-add-rule">+ Regla</button>
                </div>`);
            const rulesSub = pageHtml.find('.rules-sub-container');
            (page.rules || []).forEach((rule, rIdx) => {
                rulesSub.append(`
                    <div class="slt-rule-item" data-ridx="${rIdx}">
                        <input type="text" placeholder="Nombre" value="${rule.name || ''}" class="slt-r-name">
                        <input type="text" placeholder='Selector' value='${rule.selector || ""}' class="slt-r-sel">
                        <span class="slt-delete slt-remove-rule">x</span>
                    </div>`);
            });
            container.append(pageHtml);
        });
        $('#slt_hierarchical_tracking_input').val(JSON.stringify(data));
    }

    $('#slt-add-page').on('click', () => { data.push({url: '', rules: []}); render(); });
    $(document).on('click', '.slt-add-rule', function() { 
        const pIdx = $(this).closest('.slt-page-box').data('pidx');
        data[pIdx].rules.push({name: '', selector: ''}); render(); 
    });
    // Sincronización de inputs y eliminación...
    $(document).on('input', '.slt-page-url, .slt-r-name, .slt-r-sel', function() {
        const pIdx = $(this).closest('.slt-page-box').data('pidx');
        if ($(this).hasClass('slt-page-url')) data[pIdx].url = $(this).val();
        else {
            const rIdx = $(this).closest('.slt-rule-item').data('ridx');
            if ($(this).hasClass('slt-r-name')) data[pIdx].rules[rIdx].name = $(this).val();
            else data[pIdx].rules[rIdx].selector = $(this).val();
        }
        $('#slt_hierarchical_tracking_input').val(JSON.stringify(data));
    });
    $(document).on('click', '.slt-remove-page', function() { data.splice($(this).closest('.slt-page-box').data('pidx'), 1); render(); });
    $(document).on('click', '.slt-remove-rule', function() { 
        data[$(this).closest('.slt-page-box').data('pidx')].rules.splice($(this).closest('.slt-rule-item').data('ridx'), 1); render(); 
    });
    render();
});