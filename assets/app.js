/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
// import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import 'bootstrap';
import { Alert } from 'bootstrap';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert[data-timeout]').forEach((alertEl) => {
    setTimeout(
      () => {
        Alert.getOrCreateInstance(alertEl).close();
      },
      parseInt(alertEl.dataset.timeout, 10),
    );
  });
});
