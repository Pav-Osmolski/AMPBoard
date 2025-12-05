// assets/js/main.js
import '../scss/style.scss';

import {initToggleAccordion} from './modules/accordion.js';
import {toggleApache} from './modules/apache.js';
import {initCollapseToggle} from './modules/collapseToggle.js';
import {setColumnWidth, cycleColumnWidth, initColumnFeatures} from './modules/columns.js';
import {initClock} from './modules/clock.js';
import {initDockConfig} from './modules/dock.js';
import {initApacheErrorLog, initPhpErrorLog} from './modules/error.js';
import {initExportModule} from './modules/export.js';
import {initFoldersConfig} from './modules/folders.js';
import {initLinkTemplates} from './modules/linkTemplates.js';
import {initSearch} from './modules/search.js';
import {initClearStorageButton} from './modules/settings.js';
import {autoHideConfirmationMessage} from './modules/settings.js';
import {initSystemMonitoring} from './modules/system.js';
import {initThemeSwitcher} from './modules/theme.js';
import {setupVhostCertButtons} from './modules/vhosts.js';
import {initViewToggles} from './modules/view.js';

// Initialise on page load
initCollapseToggle();
initColumnFeatures();
initClock();
initDockConfig();
initFoldersConfig();
initLinkTemplates();
initApacheErrorLog();
initPhpErrorLog();
initExportModule();
initSearch();
initClearStorageButton();
autoHideConfirmationMessage();
initSystemMonitoring();
initThemeSwitcher();
setupVhostCertButtons();
initToggleAccordion();
initViewToggles();
