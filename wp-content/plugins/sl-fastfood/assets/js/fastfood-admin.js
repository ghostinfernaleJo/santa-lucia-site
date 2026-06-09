jQuery(function ($) {
    'use strict';

    var saveTimers = {};

    /* ================================================================
       PLANNING — Auto-sauvegarde quand une case est cochee/decochee
    ================================================================ */
    $(document).on('change', '.sl-ff-day-cb', function () {
        var $row  = $(this).closest('tr.sl-ff-meal-row');
        var id    = $row.data('id');
        var $icon = $row.find('.sl-ff-save-icon');

        var jours = [];
        $row.find('.sl-ff-day-cb:checked').each(function () {
            jours.push($(this).val());
        });

        clearTimeout(saveTimers[id]);
        $icon.attr('class', 'sl-ff-save-icon sl-ff-saving').html('&#8987;');

        saveTimers[id] = setTimeout(function () {
            $.post(slFF.ajaxurl, {
                action:  'sl_ff_save_planning',
                nonce:   slFF.nonce,
                post_id: id,
                jours:   jours
            })
            .done(function (res) {
                if (res.success) {
                    $icon.attr('class', 'sl-ff-save-icon sl-ff-saved').html('&#10003;');
                    if (slFF.todayJour && jours.indexOf(slFF.todayJour) !== -1) {
                        $row.addClass('sl-ff-meal-dispo');
                    } else {
                        $row.removeClass('sl-ff-meal-dispo');
                    }
                    setTimeout(function () { $icon.html('').attr('class', 'sl-ff-save-icon'); }, 2000);
                } else {
                    $icon.attr('class', 'sl-ff-save-icon sl-ff-error').html('&#10007;');
                }
            })
            .fail(function () {
                $icon.attr('class', 'sl-ff-save-icon sl-ff-error').html('&#10007;');
            });
        }, 350);
    });

    /* ================================================================
       PROMOTIONS — Auto-sauvegarde quand un champ change
    ================================================================ */
    $(document).on('change', '.sl-ff-promo-pct, .sl-ff-promo-debut, .sl-ff-promo-fin', function () {
        var $row  = $(this).closest('tr.sl-ff-meal-row');
        var id    = $row.data('id');
        var $icon = $row.find('.sl-ff-save-icon');
        var key   = 'promo_' + id;

        clearTimeout(saveTimers[key]);
        $icon.attr('class', 'sl-ff-save-icon sl-ff-saving').html('&#8987;');

        saveTimers[key] = setTimeout(function () {
            $.post(slFF.ajaxurl, {
                action:      'sl_ff_save_promo',
                nonce:       slFF.nonce,
                post_id:     id,
                promo_pct:   $row.find('.sl-ff-promo-pct').val()   || 0,
                promo_debut: $row.find('.sl-ff-promo-debut').val()  || '',
                promo_fin:   $row.find('.sl-ff-promo-fin').val()    || ''
            })
            .done(function (res) {
                if (res.success) {
                    $icon.attr('class', 'sl-ff-save-icon sl-ff-saved').html('&#10003;');
                    // Mettre a jour la classe promo active
                    if (res.data && res.data.est_promo) {
                        $row.addClass('sl-ff-promo-active');
                    } else {
                        $row.removeClass('sl-ff-promo-active');
                    }
                    setTimeout(function () { $icon.html('').attr('class', 'sl-ff-save-icon'); }, 2000);
                } else {
                    $icon.attr('class', 'sl-ff-save-icon sl-ff-error').html('&#10007;');
                }
            })
            .fail(function () {
                $icon.attr('class', 'sl-ff-save-icon sl-ff-error').html('&#10007;');
            });
        }, 600);
    });

    /* ================================================================
       FILTRES PLANNING (recherche repas + agence)
    ================================================================ */
    function slFfApplyPlanningFilters() {
        var agence = $('#sl-ff-agence-filter').val() || '';
        var search = ($('#sl-ff-meal-search').val() || '').toLowerCase();

        $('tr.sl-ff-meal-row').each(function () {
            var $row = $(this);
            var mealName = ($row.find('.sl-ff-plat-nom').text() || '').toLowerCase();
            var mealAgency = ($row.find('.sl-ff-plat-agence').text() || '').toLowerCase();
            var categoryName = ($row.prevAll('tr.sl-ff-cat-row:first').text() || '').toLowerCase();
            var searchableText = mealName + ' ' + mealAgency + ' ' + categoryName;
            var agenceMatch = !agence || $row.data('agence') === agence;
            var searchMatch = !search || searchableText.indexOf(search) !== -1;

            $row.toggle(agenceMatch && searchMatch);
        });

        $('tr.sl-ff-cat-row').each(function () {
            var $vis = $(this).nextUntil('tr.sl-ff-cat-row', 'tr.sl-ff-meal-row:visible');
            $(this).toggle($vis.length > 0);
        });
    }

    $('#sl-ff-agence-filter').on('change', slFfApplyPlanningFilters);
    $('#sl-ff-meal-search').on('input', slFfApplyPlanningFilters);

    /* ================================================================
       IMAGE REPAS — selection simple depuis la mediatheque WP
    ================================================================ */
    var imageFrame = null;

    $(document).on('click', '.sl-ff-image-select', function (e) {
        e.preventDefault();

        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }

        imageFrame = wp.media({
            title: 'Choisir une image pour le repas',
            button: { text: 'Utiliser cette image' },
            library: { type: 'image' },
            multiple: false
        });

        imageFrame.on('select', function () {
            var attachment = imageFrame.state().get('selection').first().toJSON();
            var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

            $('#sl_ff_thumbnail_id').val(attachment.id);
            $('#sl_ff_image_preview')
                .addClass('has-image')
                .html('<img src="' + url + '" alt="">');
            $('.sl-ff-image-select').text("Remplacer l'image");
            $('.sl-ff-image-remove').prop('disabled', false);
        });

        imageFrame.open();
    });

    $(document).on('click', '.sl-ff-image-remove', function (e) {
        e.preventDefault();
        $('#sl_ff_thumbnail_id').val('0');
        $('#sl_ff_image_preview')
            .removeClass('has-image')
            .html('<span class="dashicons dashicons-format-image"></span><strong>Aucune image</strong>');
        $('.sl-ff-image-select').text('Ajouter une image');
        $(this).prop('disabled', true);
    });
});
