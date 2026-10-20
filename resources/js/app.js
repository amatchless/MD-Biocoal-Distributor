import Alpine from 'alpinejs';
import onboardingForm from './onboarding';

window.Alpine = Alpine;

Alpine.data('onboardingForm', onboardingForm);

Alpine.start();
