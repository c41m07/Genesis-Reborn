export default {
    multipass: true,
    js2svg: {
        indent: 2,
        pretty: true,
    },
    plugins: [
        'preset-default',
        {
            name: 'removeViewBox',
            active: false,
        },
        {
            name: 'removeDimensions',
            active: true,
        },
        {
            name: 'sortAttrs',
            active: true,
        },
    ],
};
