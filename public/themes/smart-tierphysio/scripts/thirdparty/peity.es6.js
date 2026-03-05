// Peity plugin version 4.0.0
// Converted to ES6 module with no jQuery dependency to be used with SmartAdmin WebApp


// Helper functions
function extend(obj1, obj2) {
  for (const key in obj2) {
    if (obj2.hasOwnProperty(key)) {
      obj1[key] = obj2[key];
    }
  }
  return obj1;
}

function isFunction(obj) {
  return typeof obj === 'function';
}

function createSvgElement(tag, attrs) {
  const el = document.createElementNS('http://www.w3.org/2000/svg', tag);
  for (const key in attrs) {
    if (attrs.hasOwnProperty(key)) {
      el.setAttribute(key, attrs[key]);
    }
  }
  return el;
}

// Check SVG support
const svgSupported = 'createElementNS' in document &&
  createSvgElement('svg', {}).createSVGRect;

class Peity {
  constructor(element, type, opts) {
    this.element = element;
    this.type = type;
    this.opts = opts;
  }

  draw() {
    const opts = this.opts;
    Peity.graphers[this.type].call(this, opts);
    if (opts.after) opts.after.call(this, opts);
  }

  fill() {
    const fill = this.opts.fill;
    return isFunction(fill)
      ? fill
      : (_, i) => fill[i % fill.length];
  }

  prepare(width, height) {
    if (!this.svg) {
      this.element.style.display = 'none';
      this.svg = createSvgElement('svg', { 'class': 'peity' });
      this.element.parentNode.insertBefore(this.svg, this.element.nextSibling);
    }

    // Clear existing content
    while (this.svg.firstChild) {
      this.svg.removeChild(this.svg.firstChild);
    }

    this.svg.setAttribute('height', height);
    this.svg.setAttribute('width', width);

    return this.svg;
  }

  values() {
    return this.element.textContent
      .split(this.opts.delimiter)
      .map(value => parseFloat(value));
  }

  static defaults = {};
  static graphers = {};

  static register(type, defaults, grapher) {
    this.defaults[type] = defaults;
    this.graphers[type] = grapher;
  }
}

function createPeity(element, type, options) {
  if (!svgSupported) return;

  const opts = extend({}, Peity.defaults[type]);
  extend(opts, options || {});

  let chart = element._peity;

  if (chart) {
    if (type) chart.type = type;
    extend(chart.opts, opts);
  } else {
    chart = new Peity(element, type, opts);
    element._peity = chart;

    element.addEventListener('change', () => {
      chart.draw();
    });
  }

  chart.draw();
  return chart;
}

// Register pie chart
Peity.register('pie', {
  fill: ['#ff9900', '#fff4dd', '#ffc66e'],
  radius: 8
}, function (opts) {
  if (!opts.delimiter) {
    const delimiter = this.element.textContent.match(/[^0-9\.]/);
    opts.delimiter = delimiter ? delimiter[0] : ",";
  }

  let values = this.values().map(n => n > 0 ? n : 0);

  if (opts.delimiter == "/") {
    const v1 = values[0];
    const v2 = values[1];
    values = [v1, Math.max(0, v2 - v1)];
  }

  let sum = values.reduce((a, b) => a + b, 0);

  if (!sum) {
    values = [0, 1];
    sum = 1;
  }

  const diameter = opts.radius * 2;
  const svg = this.prepare(opts.width || diameter, opts.height || diameter);
  const width = parseInt(svg.getAttribute('width'));
  const height = parseInt(svg.getAttribute('height'));
  const cx = width / 2;
  const cy = height / 2;
  const radius = Math.min(cx, cy);
  let innerRadius = opts.innerRadius;

  if (this.type == 'donut' && !innerRadius) {
    innerRadius = radius * 0.5;
  }

  const pi = Math.PI;
  const fill = this.fill();

  const scale = (value, radius) => {
    const radians = value / sum * pi * 2 - pi / 2;
    return [
      radius * Math.cos(radians) + cx,
      radius * Math.sin(radians) + cy
    ];
  };

  let cumulative = 0;

  for (let i = 0; i < values.length; i++) {
    const value = values[i];
    const portion = value / sum;
    let node;

    if (portion === 0) continue;

    if (portion === 1) {
      if (innerRadius) {
        const x2 = cx - 0.01;
        const y1 = cy - radius;
        const y2 = cy - innerRadius;

        node = createSvgElement('path', {
          d: [
            'M', cx, y1,
            'A', radius, radius, 0, 1, 1, x2, y1,
            'L', x2, y2,
            'A', innerRadius, innerRadius, 0, 1, 0, cx, y2
          ].join(' '),
          'data-value': value
        });
      } else {
        node = createSvgElement('circle', {
          cx: cx,
          cy: cy,
          'data-value': value,
          r: radius
        });
      }
    } else {
      const cumulativePlusValue = cumulative + value;

      const d = ['M'].concat(
        scale(cumulative, radius),
        'A', radius, radius, 0, portion > 0.5 ? 1 : 0, 1,
        scale(cumulativePlusValue, radius),
        'L'
      );

      if (innerRadius) {
        d.push(
          ...scale(cumulativePlusValue, innerRadius),
          'A', innerRadius, innerRadius, 0, portion > 0.5 ? 1 : 0, 0,
          ...scale(cumulative, innerRadius)
        );
      } else {
        d.push(cx, cy);
      }

      cumulative += value;

      node = createSvgElement('path', {
        d: d.join(" "),
        'data-value': value
      });
    }

    node.setAttribute('fill', fill.call(this, value, i, values));
    svg.appendChild(node);
  }
});

// Register donut chart
Peity.register('donut',
  extend({}, Peity.defaults.pie),
  function (opts) {
    Peity.graphers.pie.call(this, opts);
  }
);

// Register line chart
Peity.register('line', {
  delimiter: ",",
  fill: "#c6d9fd",
  height: 16,
  min: 0,
  stroke: "#4d89f9",
  strokeWidth: 1,
  width: 32
}, function (opts) {
  const values = this.values();
  if (values.length == 1) values.push(values[0]);
  const max = Math.max.apply(Math, opts.max == undefined ? values : values.concat(opts.max));
  const min = Math.min.apply(Math, opts.min == undefined ? values : values.concat(opts.min));

  const svg = this.prepare(opts.width, opts.height);
  const strokeWidth = opts.strokeWidth;
  const width = parseInt(svg.getAttribute('width'));
  const height = parseInt(svg.getAttribute('height')) - strokeWidth;
  const diff = max - min;

  function xScale(input) {
    return input * (width / (values.length - 1));
  }

  function yScale(input) {
    let y = height;
    if (diff) {
      y -= ((input - min) / diff) * height;
    }
    return y + strokeWidth / 2;
  }

  const zero = yScale(Math.max(min, 0));
  const coords = [0, zero];

  for (let i = 0; i < values.length; i++) {
    coords.push(
      xScale(i),
      yScale(values[i])
    );
  }

  coords.push(width, zero);

  if (opts.fill) {
    const polygon = createSvgElement('polygon', {
      fill: opts.fill,
      points: coords.join(' ')
    });
    svg.appendChild(polygon);
  }

  if (strokeWidth) {
    const polyline = createSvgElement('polyline', {
      fill: 'none',
      points: coords.slice(2, coords.length - 2).join(' '),
      stroke: opts.stroke,
      'stroke-width': strokeWidth,
      'stroke-linecap': 'square'
    });
    svg.appendChild(polyline);
  }
});

// Register bar chart
Peity.register('bar', {
  delimiter: ",",
  fill: ["#4D89F9"],
  height: 16,
  min: 0,
  padding: 0.1,
  width: 32
}, function (opts) {
  const values = this.values();
  const max = Math.max.apply(Math, opts.max == undefined ? values : values.concat(opts.max));
  const min = Math.min.apply(Math, opts.min == undefined ? values : values.concat(opts.min));

  const svg = this.prepare(opts.width, opts.height);
  const width = parseInt(svg.getAttribute('width'));
  const height = parseInt(svg.getAttribute('height'));
  const diff = max - min;
  const padding = opts.padding;
  const fill = this.fill();

  function xScale(input) {
    return input * width / values.length;
  }

  function yScale(input) {
    return height - (
      diff
        ? ((input - min) / diff) * height
        : 1
    );
  }

  for (let i = 0; i < values.length; i++) {
    const x = xScale(i + padding);
    const w = xScale(i + 1 - padding) - x;
    const value = values[i];
    const valueY = yScale(value);
    let y1 = valueY;
    let y2 = valueY;
    let h;

    if (!diff) {
      h = 1;
    } else if (value < 0) {
      y1 = yScale(Math.min(max, 0));
    } else {
      y2 = yScale(Math.max(min, 0));
    }

    h = y2 - y1;

    if (h === 0) {
      h = 1;
      if (max > 0 && diff) y1--;
    }

    const rect = createSvgElement('rect', {
      'data-value': value,
      fill: fill.call(this, value, i, values),
      x: x,
      y: y1,
      width: w,
      height: h
    });

    svg.appendChild(rect);
  }
});

// Export the API
export const PeityAPI = {
  create: createPeity,
  defaults: Peity.defaults,
  graphers: Peity.graphers,
  register: Peity.register
};