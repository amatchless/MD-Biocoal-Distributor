import Alpine from 'alpinejs';
import attendanceClock from './attendance';
import onboardingForm from './onboarding';

window.Alpine = Alpine;

Alpine.data('attendanceClock', attendanceClock);
Alpine.data('onboardingForm', onboardingForm);

Alpine.start();
