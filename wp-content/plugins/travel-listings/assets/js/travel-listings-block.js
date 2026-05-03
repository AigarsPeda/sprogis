(function (blocks, element, blockEditor, components, i18n) {
    var el = element.createElement;
    var Fragment = element.Fragment;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var RangeControl = components.RangeControl;
    var TextControl = components.TextControl;
    var TextareaControl = components.TextareaControl;
    var ToggleControl = components.ToggleControl;
    var Placeholder = components.Placeholder;
    var __ = i18n.__;

    blocks.registerBlockType('travel-listings/listings', {
        title: __('Travel Listings', 'travel-listings'),
        description: __('Display travel listings without manually pasting a shortcode.', 'travel-listings'),
        icon: 'airplane',
        category: 'widgets',
        keywords: [
            __('travel', 'travel-listings'),
            __('listings', 'travel-listings'),
            __('events', 'travel-listings')
        ],
        attributes: {
            postsPerPage: {
                type: 'number',
                default: 12
            },
            category: {
                type: 'string',
                default: ''
            },
            showFilter: {
                type: 'boolean',
                default: true
            },
            showHero: {
                type: 'boolean',
                default: true
            },
            heroTitle: {
                type: 'string',
                default: ''
            },
            heroSubtitle: {
                type: 'string',
                default: ''
            },
            heroImage: {
                type: 'string',
                default: ''
            }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        {
                            title: __('Travel Listings Settings', 'travel-listings'),
                            initialOpen: true
                        },
                        el(RangeControl, {
                            label: __('Posts per page', 'travel-listings'),
                            value: attributes.postsPerPage,
                            onChange: function (value) {
                                setAttributes({ postsPerPage: value || 1 });
                            },
                            min: 1,
                            max: 48
                        }),
                        el(TextControl, {
                            label: __('Category slug', 'travel-listings'),
                            help: __('Optional. Example: summer-trips', 'travel-listings'),
                            value: attributes.category,
                            onChange: function (value) {
                                setAttributes({ category: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Show filter', 'travel-listings'),
                            checked: attributes.showFilter,
                            onChange: function (value) {
                                setAttributes({ showFilter: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Show hero section', 'travel-listings'),
                            checked: attributes.showHero,
                            onChange: function (value) {
                                setAttributes({ showHero: value });
                            }
                        }),
                        el(TextControl, {
                            label: __('Hero title override', 'travel-listings'),
                            help: __('Leave empty to use Hero Settings from the plugin.', 'travel-listings'),
                            value: attributes.heroTitle,
                            onChange: function (value) {
                                setAttributes({ heroTitle: value });
                            }
                        }),
                        el(TextareaControl, {
                            label: __('Hero subtitle override', 'travel-listings'),
                            value: attributes.heroSubtitle,
                            onChange: function (value) {
                                setAttributes({ heroSubtitle: value });
                            }
                        }),
                        el(TextControl, {
                            label: __('Hero image URL override', 'travel-listings'),
                            value: attributes.heroImage,
                            onChange: function (value) {
                                setAttributes({ heroImage: value });
                            }
                        })
                    )
                ),
                el(
                    Placeholder,
                    {
                        icon: 'airplane',
                        label: __('Travel Listings', 'travel-listings'),
                        instructions: __('This block shows your travel listings on the front end.', 'travel-listings')
                    },
                    el('div', { style: { marginTop: '12px' } },
                        el('p', {}, __('How to use this block:', 'travel-listings')),
                        el('ol', { style: { marginLeft: '20px' } },
                            el('li', {}, __('Add listing posts in Travel Listings > Add New.', 'travel-listings')),
                            el('li', {}, __('Set the card image with the Featured Image panel on each listing.', 'travel-listings')),
                            el('li', {}, __('Edit the hero title, subtitle, and background image in Travel Listings > Hero Settings.', 'travel-listings')),
                            el('li', {}, __('Use the block settings in the right sidebar for filters, category slug, and posts per page.', 'travel-listings'))
                        ),
                        el('p', { style: { marginTop: '12px', marginBottom: 0, color: '#50575e' } },
                            __('The full design will appear on the published page, not in this editor preview.', 'travel-listings')
                        )
                    )
                )
            );
        },
        save: function () {
            return null;
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);
