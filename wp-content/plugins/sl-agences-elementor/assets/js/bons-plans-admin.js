/* jshint esversion: 6 */
jQuery(function ($) {
    'use strict';

    var frame;
    var $preview = $('#sl-image-preview');
    var $imageId = $('#sl-image-id');
    var $btnRemove = $('#sl-btn-remove');

    // Ouvrir le media uploader
    $('#sl-btn-upload, #sl-image-preview').on('click', function () {
        if (frame) { frame.open(); return; }

        frame = wp.media({
            title: 'Choisir une image produit',
            button: { text: 'Utiliser cette image' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var url = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;

            $imageId.val(attachment.id);
            $preview.css('background-image', 'url(' + url + ')');
            $preview.find('.sl-bp-upload-placeholder').hide();
            $btnRemove.show();
        });

        frame.open();
    });

    // Supprimer l'image
    $btnRemove.on('click', function (e) {
        e.stopPropagation();
        $imageId.val('0');
        $preview.css('background-image', '');
        $preview.find('.sl-bp-upload-placeholder').show();
        $(this).hide();
    });

    // Calculer la réduction en temps réel
    $('#sl-prix-av, #sl-prix-ap').on('input', function () {
        var av = parseFloat($('#sl-prix-av').val()) || 0;
        var ap = parseFloat($('#sl-prix-ap').val()) || 0;
        if (av > 0 && ap > 0 && ap < av) {
            var pct = Math.round(((av - ap) / av) * 100);
            $('#sl-reduction-preview').text('-' + pct + '%').show();
        } else {
            $('#sl-reduction-preview').hide();
        }
    });

    // --- QUICK IMAGE AJAX (Admin List) ---
    var quickImageFrame;
    $(document).on('click', '.sl-quick-img-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $wrapper = $btn.closest('.sl-quick-img-wrapper');
        var postId = $wrapper.data('post-id');
        var $loader = $wrapper.find('.sl-quick-img-loader');

        if (quickImageFrame) {
            quickImageFrame.open();
        } else {
            quickImageFrame = wp.media({
                title: 'Choisir une image pour le Bon Plan',
                button: { text: 'Définir comme image' },
                multiple: false,
                library: { type: 'image' }
            });
        }

        quickImageFrame.off('select').on('select', function() {
            var attachment = quickImageFrame.state().get('selection').first().toJSON();
            var imgUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            
            $loader.css('display', 'flex'); // Show loader

            $.post(ajaxurl, {
                action: 'sl_quick_image_save',
                post_id: postId,
                image_id: attachment.id,
                security: typeof slBpAdmin !== 'undefined' ? slBpAdmin.nonce : ''
            }, function(response) {
                $loader.hide(); // Hide loader
                if (response.success) {
                    // Remplacer le contenu par la nouvelle image
                    $wrapper.find('.sl-quick-img-btn').remove();
                    $wrapper.prepend('<img src="' + imgUrl + '" width="50" height="50" style="object-fit:cover; border-radius:4px; cursor:pointer;" class="sl-quick-img-btn" title="Changer l\'image">');
                } else {
                    alert('Erreur lors de la sauvegarde : ' + (response.data || 'Erreur inconnue'));
                }
            }).fail(function() {
                $loader.hide();
                alert('Erreur réseau lors de la sauvegarde.');
            });
        });

        quickImageFrame.open();
    });
});
