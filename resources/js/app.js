import Alpine from 'alpinejs';
import attendanceClock from './attendance';
import onboardingForm from './onboarding';
import candidateUpload from './candidate-upload';

window.Alpine = Alpine;

Alpine.data('attendanceClock', attendanceClock);
Alpine.data('onboardingForm', onboardingForm);
Alpine.data('candidateUpload', candidateUpload);

Alpine.start();
