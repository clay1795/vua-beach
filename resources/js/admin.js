import './vendor-ui';
import './form-state';
import './validation-errors';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;
