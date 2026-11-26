<?php
/**
 * School Manager - Modern Liquid Glass & Classic Navy/Teal Mobile UX Layer
 * Follows the clean institutional design system:
 * Deep Navy (#0F2951), Teal (#088E91), neutral Slate (#F8FAFC), and crisp typography.
 */
if (!defined('GLPI_ROOT')) { die('No direct access'); }
$smMobileStepsLabel = function_exists('plugin_schoolmanager_tr') ? plugin_schoolmanager_tr('steps_completed', 'pasos completados') : 'pasos completados';
?>
<style id="sm-form-mobile-ux">
/* ==========================================================================
   CLASSIC INSTITUTIONAL DESIGN SYSTEM (Desktop + Mobile)
   Deep Navy (#0F2951) + Teal (#088E91)
   ========================================================================== */
.pc-form-view {
  --sm-navy: #0F2951;
  --sm-teal: #088E91;
  --sm-teal-light: #0DADB0;
  --sm-teal-soft: #F0FDFA;
  --sm-teal-border: #99F6E4;
  --sm-glass-bg: rgba(255, 255, 255, 0.88);
  --sm-glass-border: rgba(255, 255, 255, 0.85);
  --sm-glass-shadow: 0 10px 30px -5px rgba(15, 41, 81, 0.05), 0 4px 12px -2px rgba(15, 41, 81, 0.03);
  --sm-ink: #0F172A;
  --sm-muted: #64748B;
  --sm-line: #E2E8F0;
  
  width: 100% !important;
  max-width: 100% !important;
  margin: 0 !important;
  padding: 0 !important;
  box-sizing: border-box !important;
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
}

.pc-form-view * { box-sizing: border-box !important; }

.pc-form-view .pc-form-container {
  width: 100% !important;
  max-width: 100% !important;
  margin: 0 !important;
  padding: 0 !important;
}

/* Glassmorphism Section Cards */
.pc-form-view .pc-section-card {
  background: var(--sm-glass-bg) !important;
  backdrop-filter: blur(18px) saturate(180%) !important;
  -webkit-backdrop-filter: blur(18px) saturate(180%) !important;
  border: 1px solid var(--sm-glass-border) !important;
  border-radius: 22px !important;
  padding: 24px !important;
  box-shadow: var(--sm-glass-shadow) !important;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease !important;
  scroll-margin-top: 20px;
  position: relative !important;
  overflow: hidden !important;
}
.pc-form-view .pc-section-card:hover {
  border-color: rgba(8, 142, 145, 0.25) !important;
  box-shadow: 0 14px 34px -4px rgba(15, 41, 81, 0.08), 0 6px 16px -2px rgba(15, 41, 81, 0.04) !important;
}

.pc-form-view .pc-section-header {
  border-bottom: 1px solid rgba(226, 232, 240, 0.7) !important;
  padding-bottom: 14px !important;
  margin-bottom: 18px !important;
}
.pc-form-view .pc-section-step {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  width: 30px !important;
  height: 30px !important;
  min-width: 30px !important;
  border-radius: 10px !important;
  background: #0F2951 !important;
  color: #FFFFFF !important;
  font-size: 13.5px !important;
  font-weight: 850 !important;
  line-height: 1 !important;
  box-shadow: 0 2px 6px rgba(15, 41, 81, 0.28) !important;
}
.pc-form-view .pc-section-title {
  font-size: 17px !important;
  font-weight: 850 !important;
  color: #0F2951 !important;
  letter-spacing: -0.015em !important;
}

/* Rounded Inputs & Focus */
.pc-form-view .pc-field-input,
.pc-form-view .pc-field-textarea,
.pc-form-view input:not([type=checkbox]):not([type=radio]),
.pc-form-view select,
.pc-form-view textarea {
  border: 1.5px solid #CBD5E1 !important;
  border-radius: 14px !important;
  background: rgba(255, 255, 255, 0.95) !important;
  padding: 11px 16px !important;
  font-size: 14px !important;
  font-weight: 650 !important;
  color: #0F172A !important;
  transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.pc-form-view .pc-field-input:focus,
.pc-form-view .pc-field-textarea:focus,
.pc-form-view input:not([type=checkbox]):not([type=radio]):focus,
.pc-form-view select:focus,
.pc-form-view textarea:focus {
  border-color: #088E91 !important;
  background: #FFFFFF !important;
  box-shadow: 0 0 0 3.5px rgba(8, 142, 145, 0.18) !important;
  outline: none !important;
}

/* Desktop Step Tracker with Classic Palette */
.pc-form-view .pc-steps-tracker {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 22px;
}
.pc-form-view .pc-step-pill {
  display: flex !important;
  align-items: center !important;
  gap: 10px !important;
  padding: 12px 16px !important;
  border-radius: 16px !important;
  background: rgba(255, 255, 255, 0.85) !important;
  backdrop-filter: blur(12px) !important;
  border: 1.5px solid rgba(226, 232, 240, 0.9) !important;
  box-shadow: 0 2px 8px rgba(15, 41, 81, 0.03) !important;
  cursor: pointer !important;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.pc-form-view .pc-step-pill:hover {
  border-color: #088E91 !important;
  background: #FFFFFF !important;
  box-shadow: 0 6px 18px rgba(8, 142, 145, 0.12) !important;
  transform: translateY(-1px) !important;
}
.pc-form-view .pc-step-pill-num {
  width: 26px !important;
  height: 26px !important;
  border-radius: 9px !important;
  background: #E2E8F0 !important;
  color: #0F2951 !important;
  font-size: 12.5px !important;
  font-weight: 850 !important;
  display: inline-grid !important;
  place-items: center !important;
  transition: all 0.2s ease !important;
}
.pc-form-view .pc-step-pill.is-current {
  border-color: #088E91 !important;
  background: #F0FDFA !important;
  box-shadow: 0 0 0 3px rgba(8, 142, 145, 0.15) !important;
}
.pc-form-view .pc-step-pill.is-current .pc-step-pill-num {
  background: #0F2951 !important;
  color: #FFFFFF !important;
  box-shadow: 0 2px 6px rgba(15, 41, 81, 0.3) !important;
}
.pc-form-view .pc-step-pill.is-done {
  border-color: #99F6E4 !important;
  background: #F0FDFA !important;
}
.pc-form-view .pc-step-pill.is-done .pc-step-pill-num {
  background: #088E91 !important;
  color: #FFFFFF !important;
}

/* Location Interactive Card (Clean, Minimalist, Frosted Glass) */
.pc-form-view .pc-location-interactive-card {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  gap: 16px !important;
  padding: 18px 22px !important;
  border-radius: 18px !important;
  background: rgba(248, 250, 252, 0.88) !important;
  border: 1.5px solid #CBD5E1 !important;
  backdrop-filter: blur(12px) !important;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
  flex-wrap: wrap !important;
}
.pc-form-view .pc-location-interactive-card.is-selected {
  border-color: #088E91 !important;
  background: #F0FDFA !important;
  box-shadow: 0 4px 16px rgba(8, 142, 145, 0.12) !important;
}
.pc-form-view .pc-loc-icon-bubble {
  width: 46px !important;
  height: 46px !important;
  border-radius: 14px !important;
  background: #F0FDFA !important;
  color: #088E91 !important;
  border: 1px solid #99F6E4 !important;
  display: inline-grid !important;
  place-items: center !important;
  flex-shrink: 0 !important;
}
.pc-form-view .pc-location-interactive-card.is-selected .pc-loc-icon-bubble {
  background: #0F2951 !important;
  color: #FFFFFF !important;
  border-color: transparent !important;
  box-shadow: 0 4px 12px rgba(15, 41, 81, 0.25) !important;
}
.pc-form-view .pc-loc-name {
  font-size: 15.5px !important;
  font-weight: 850 !important;
  color: #0F2951 !important;
  margin: 0 !important;
}
.pc-form-view .pc-loc-meta {
  font-size: 12.5px !important;
  font-weight: 600 !important;
  color: #64748B !important;
  margin: 2px 0 0 !important;
}

/* Action Buttons: Solid Classic Deep Navy / Teal */
.pc-form-view .pc-btn-primary,
.pc-form-view .pc-btn-action.pc-btn-primary,
.pc-form-view button[type=submit].pc-btn-primary {
  background: #0F2951 !important;
  background-image: none !important;
  border: 1px solid #0F2951 !important;
  color: #FFFFFF !important;
  border-radius: 14px !important;
  padding: 0 22px !important;
  min-height: 46px !important;
  font-size: 14px !important;
  font-weight: 800 !important;
  box-shadow: 0 4px 14px rgba(15, 41, 81, 0.22) !important;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.pc-form-view .pc-btn-primary:hover,
.pc-form-view .pc-btn-action.pc-btn-primary:hover,
.pc-form-view button[type=submit].pc-btn-primary:hover {
  background: #088E91 !important;
  border-color: #088E91 !important;
  box-shadow: 0 6px 20px rgba(8, 142, 145, 0.32) !important;
  transform: translateY(-1px) !important;
}

.pc-form-view .pc-btn-secondary,
.pc-form-view .pc-btn-action.pc-btn-secondary {
  background: rgba(255, 255, 255, 0.9) !important;
  border: 1.5px solid #CBD5E1 !important;
  border-radius: 14px !important;
  color: #0F2951 !important;
  padding: 0 20px !important;
  min-height: 46px !important;
  font-size: 14px !important;
  font-weight: 750 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
  transition: all 0.2s ease !important;
}
.pc-form-view .pc-btn-secondary:hover,
.pc-form-view .pc-btn-action.pc-btn-secondary:hover {
  background: #F8FAFC !important;
  border-color: #94A3B8 !important;
  color: #0F2951 !important;
}

.pc-form-view .pc-action-bar {
  width: 100% !important;
  max-width: 100% !important;
  box-sizing: border-box !important;
  margin: 22px 0 0 0 !important;
  padding: 18px 24px !important;
  background: var(--sm-glass-bg) !important;
  backdrop-filter: blur(18px) saturate(180%) !important;
  -webkit-backdrop-filter: blur(18px) saturate(180%) !important;
  border: 1px solid var(--sm-glass-border) !important;
  border-radius: 20px !important;
  box-shadow: var(--sm-glass-shadow) !important;
}

/* Quick Suggestion Chips */
.pc-form-view .pc-quick-chip {
  border: 1px solid #CBD5E1 !important;
  background: rgba(255, 255, 255, 0.9) !important;
  color: #0F2951 !important;
  border-radius: 999px !important;
  padding: 6px 14px !important;
  font-size: 12px !important;
  font-weight: 750 !important;
  cursor: pointer !important;
  transition: all 0.16s ease !important;
}
.pc-form-view .pc-quick-chip:hover {
  background: #088E91 !important;
  border-color: #088E91 !important;
  color: #FFFFFF !important;
  box-shadow: 0 2px 8px rgba(8, 142, 145, 0.25) !important;
}

/* Category parent buttons (Ticket) */
.pc-form-view .pc-parent-btn {
  border-radius: 14px !important;
  background: rgba(255, 255, 255, 0.9) !important;
  border: 1.5px solid #CBD5E1 !important;
  padding: 10px 14px !important;
  color: #0F2951 !important;
  font-weight: 750 !important;
  transition: all 0.18s ease !important;
}
.pc-form-view .pc-parent-btn:hover {
  border-color: #088E91 !important;
  background: #F0FDFA !important;
}
.pc-form-view .pc-parent-btn.active {
  background: #0F2951 !important;
  border-color: #0F2951 !important;
  color: #FFFFFF !important;
  box-shadow: 0 4px 14px rgba(15, 41, 81, 0.28) !important;
}
.pc-form-view .pc-parent-btn.active span { color: #FFFFFF !important; }

/* Subcategories problem items */
.pc-form-view .pc-subcat {
  border-radius: 14px !important;
  background: #FFFFFF !important;
  border: 1.5px solid #CBD5E1 !important;
  padding: 12px 14px !important;
  transition: all 0.18s ease !important;
}
.pc-form-view .pc-subcat:hover {
  border-color: #088E91 !important;
  background: #F0FDFA !important;
}
.pc-form-view .pc-subcat.active {
  background: #0F2951 !important;
  border-color: #0F2951 !important;
  color: #FFFFFF !important;
  box-shadow: 0 4px 14px rgba(15, 41, 81, 0.28) !important;
}
.pc-form-view .pc-subcat.active b { color: #FFFFFF !important; }
.pc-form-view .pc-subcat.active small { color: #E2E8F0 !important; }

/* Asset Type Selection Tiles (Asset form) */
.pc-form-view .pc-types-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 22px;
}
.pc-form-view .pc-type-card {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  text-align: center !important;
  padding: 16px 14px !important;
  border-radius: 18px !important;
  background: rgba(255, 255, 255, 0.88) !important;
  backdrop-filter: blur(12px) !important;
  border: 1.5px solid rgba(226, 232, 240, 0.9) !important;
  box-shadow: 0 2px 8px rgba(15, 41, 81, 0.03) !important;
  cursor: pointer !important;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
  text-decoration: none !important;
}
.pc-form-view .pc-type-card:hover {
  border-color: #088E91 !important;
  transform: translateY(-2px) !important;
  box-shadow: 0 8px 20px rgba(8, 142, 145, 0.12) !important;
}
.pc-form-view .pc-type-card.active {
  background: #F0FDFA !important;
  border-color: #088E91 !important;
  box-shadow: 0 0 0 3px rgba(8, 142, 145, 0.16), 0 8px 22px rgba(8, 142, 145, 0.14) !important;
}
.pc-form-view .pc-type-icon-badge {
  width: 44px !important;
  height: 44px !important;
  border-radius: 14px !important;
  background: #F0FDFA !important;
  color: #088E91 !important;
  display: inline-grid !important;
  place-items: center !important;
  margin-bottom: 10px !important;
  transition: all 0.2s ease !important;
}
.pc-form-view .pc-type-card.active .pc-type-icon-badge {
  background: #0F2951 !important;
  color: #FFFFFF !important;
  box-shadow: 0 4px 12px rgba(15, 41, 81, 0.28) !important;
}
.pc-form-view .pc-type-card-title {
  font-size: 14px !important;
  font-weight: 850 !important;
  color: #0F2951 !important;
  line-height: 1.2 !important;
}
.pc-form-view .pc-type-card-desc {
  font-size: 11.5px !important;
  font-weight: 600 !important;
  color: #64748B !important;
  margin-top: 3px !important;
}

/* ==========================================================================
   MOBILE DEDICATED OPTIMIZATIONS (<= 768px / <= 900px)
   ========================================================================== */
@media (max-width: 900px) {
  /* Header description hidden completely on mobile per user specification */
  .sm-universal-hero .sm-hero-subtitle,
  .sm-universal-hero p.sm-hero-subtitle,
  .sm-universal-hero p,
  .sm-hero-subtitle {
    display: none !important;
  }
}

@media (max-width: 768px) {
  .pc-form-view {
    padding-bottom: calc(105px + env(safe-area-inset-bottom)) !important;
  }

  /* 16px inputs prevent auto-zoom on iOS */
  .pc-form-view input:not([type=checkbox]):not([type=radio]),
  .pc-form-view select,
  .pc-form-view textarea,
  .pc-form-view .select2-selection__rendered {
    font-size: 16px !important;
    min-height: 48px !important;
  }

  /* Native Capsule Expanding Stepper (Fits 100% Mobile Screen, No Truncation, Clean Luxury UX) */
  .pc-form-view .pc-steps-tracker {
    position: sticky !important;
    top: 6px !important;
    z-index: 60 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 6px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    margin: 0 0 14px 0 !important;
    padding: 6px 8px !important;
    border-radius: 18px !important;
    background: rgba(255, 255, 255, 0.9) !important;
    backdrop-filter: blur(20px) saturate(180%) !important;
    -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
    border: 1px solid rgba(255, 255, 255, 0.85) !important;
    box-shadow: 0 4px 14px rgba(15, 41, 81, 0.05) !important;
  }
  .pc-form-view .pc-steps-tracker::-webkit-scrollbar { display: none !important; }

  .pc-form-view .pc-step-pill {
    flex: 0 0 38px !important;
    width: 38px !important;
    min-width: 38px !important;
    max-width: 38px !important;
    height: 38px !important;
    min-height: 38px !important;
    padding: 0 !important;
    border-radius: 999px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(241, 245, 249, 0.8) !important;
    border: 1.5px solid transparent !important;
    cursor: pointer !important;
    box-sizing: border-box !important;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
  }
  .pc-form-view .pc-step-pill-num {
    width: 24px !important;
    height: 24px !important;
    border-radius: 50% !important;
    background: #CBD5E1 !important;
    color: #334155 !important;
    font-size: 11.5px !important;
    font-weight: 850 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    line-height: 1 !important;
    flex-shrink: 0 !important;
  }
  .pc-form-view .pc-step-pill .pc-step-pill-text {
    display: none !important;
  }
  .pc-form-view .pc-step-pill.is-current {
    flex: 1 1 auto !important;
    width: auto !important;
    min-width: 0 !important;
    max-width: none !important;
    padding: 0 14px 0 8px !important;
    background: #FFFFFF !important;
    border-color: #088E91 !important;
    box-shadow: 0 2px 10px rgba(8, 142, 145, 0.18) !important;
    gap: 8px !important;
  }
  .pc-form-view .pc-step-pill.is-current .pc-step-pill-num {
    background: #0F2951 !important;
    color: #FFFFFF !important;
    box-shadow: 0 2px 6px rgba(15, 41, 81, 0.3) !important;
  }
  .pc-form-view .pc-step-pill.is-current .pc-step-pill-text {
    display: inline-flex !important;
    min-width: 0 !important;
  }
  .pc-form-view .pc-step-pill.is-current .pc-step-pill-text strong {
    font-size: 12px !important;
    font-weight: 850 !important;
    color: #0F2951 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
  }
  .pc-form-view .pc-step-pill.is-current .pc-step-pill-text small { display: none !important; }

  .pc-form-view .pc-step-pill.is-done {
    background: #F0FDFA !important;
    border-color: #99F6E4 !important;
  }
  .pc-form-view .pc-step-pill.is-done .pc-step-pill-num {
    background: #088E91 !important;
    color: #FFFFFF !important;
  }

  /* Card padding and styling on mobile */
  .pc-form-view .pc-section-card {
    scroll-margin-top: 64px !important;
    padding: 18px 16px !important;
    border-radius: 18px !important;
    margin-bottom: 14px !important;
    box-sizing: border-box !important;
  }

  /* Asset Type Selection Tiles (Clean Frosted 2-Column Grid) */
  .pc-form-view .pc-types-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 10px !important;
    margin: 0 0 16px 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
  }
  .pc-form-view .pc-types-grid::-webkit-scrollbar { display: none !important; }

  .pc-form-view .pc-type-card {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 12px 14px !important;
    border-radius: 16px !important;
    background: rgba(255, 255, 255, 0.88) !important;
    backdrop-filter: blur(14px) !important;
    -webkit-backdrop-filter: blur(14px) !important;
    border: 1.5px solid rgba(226, 232, 240, 0.9) !important;
    box-shadow: 0 2px 6px rgba(15, 41, 81, 0.03) !important;
    cursor: pointer !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    min-height: 54px !important;
    text-decoration: none !important;
    box-sizing: border-box !important;
  }
  .pc-form-view .pc-type-card .pc-type-icon-badge {
    width: 34px !important;
    height: 34px !important;
    border-radius: 10px !important;
    background: #F0FDFA !important;
    color: #088E91 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 16px !important;
    margin-bottom: 0 !important;
    flex-shrink: 0 !important;
    transition: all 0.2s ease !important;
  }
  .pc-form-view .pc-type-card-title {
    font-size: 13px !important;
    font-weight: 800 !important;
    color: #0F2951 !important;
    line-height: 1.2 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
  }
  .pc-form-view .pc-type-card-desc { display: none !important; }
  .pc-form-view .pc-type-card.active {
    background: #F0FDFA !important;
    border-color: #088E91 !important;
    box-shadow: 0 0 0 2.5px rgba(8, 142, 145, 0.18), 0 6px 16px rgba(8, 142, 145, 0.12) !important;
  }
  .pc-form-view .pc-type-card.active .pc-type-icon-badge {
    background: #0F2951 !important;
    color: #FFFFFF !important;
    box-shadow: 0 3px 8px rgba(15, 41, 81, 0.28) !important;
  }

  /* Parent Categories in Ticket: Clean 2-Column Grid */
  .pc-form-view .pc-parent-list {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 8px !important;
    margin: 0 0 14px 0 !important;
    padding: 0 !important;
  }
  .pc-form-view .pc-parent-list::-webkit-scrollbar { display: none !important; }

  .pc-form-view .pc-parent-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    min-height: 44px !important;
    padding: 8px 12px !important;
    border-radius: 14px !important;
    font-size: 12.5px !important;
    font-weight: 800 !important;
    text-align: center !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;
  }

  .pc-form-view .pc-subcat-list.active {
    grid-template-columns: 1fr !important;
    max-height: none !important;
    overflow: visible !important;
  }

  /* Location Card on Mobile */
  .pc-form-view .pc-location-interactive-card {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 14px !important;
    padding: 16px !important;
  }
  .pc-form-view .pc-loc-controls {
    flex-direction: column !important;
    align-items: stretch !important;
    width: 100% !important;
    gap: 10px !important;
  }
  .pc-form-view .pc-loc-quick-select,
  .pc-form-view #pcOpenSelector,
  .pc-form-view .pc-btn-action {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
  }

  /* Fixed Bottom Action Bar with Dynamic Progress */
  .pc-form-view .pc-action-bar,
  .pc-form-view .pc-action-bar.pc-submit {
    position: fixed !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    z-index: 1040 !important;
    margin: 0 !important;
    width: 100% !important;
    border-radius: 22px 22px 0 0 !important;
    border: 0 !important;
    border-top: 1px solid rgba(255, 255, 255, 0.8) !important;
    box-shadow: 0 -8px 28px rgba(15, 41, 81, 0.08) !important;
    padding: 10px 16px calc(12px + env(safe-area-inset-bottom)) !important;
    display: grid !important;
    grid-template-columns: auto minmax(0, 1fr) !important;
    grid-template-areas: "progress progress" "cancel submit" !important;
    gap: 8px 12px !important;
    background: rgba(255, 255, 255, 0.94) !important;
    backdrop-filter: blur(24px) saturate(200%) !important;
    -webkit-backdrop-filter: blur(24px) saturate(200%) !important;
    box-sizing: border-box !important;
    transition: transform 0.22s ease !important;
  }
  .sm-m-progress-wrap {
    grid-area: progress !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    width: 100% !important;
    box-sizing: border-box !important;
  }
  .sm-m-progress {
    flex: 1 1 auto !important;
    height: 6px !important;
    border-radius: 999px !important;
    background: #E2E8F0 !important;
    overflow: hidden !important;
  }
  .sm-m-progress-fill {
    display: block !important;
    height: 100% !important;
    width: 0 !important;
    background: linear-gradient(90deg, #0F2951 0%, #088E91 100%) !important;
    border-radius: 999px !important;
    transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
  }
  .sm-m-progress-label {
    font-size: 11.5px !important;
    font-weight: 850 !important;
    color: #0F2951 !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
  }
  .pc-form-view .pc-action-bar > a,
  .pc-form-view .pc-action-bar > .pc-btn-secondary {
    grid-area: cancel !important;
    width: auto !important;
    min-width: 0 !important;
    padding: 0 16px !important;
    min-height: 48px !important;
    border-radius: 14px !important;
    font-size: 13.5px !important;
    font-weight: 750 !important;
    box-sizing: border-box !important;
  }
  .pc-form-view .pc-action-bar > button[type=submit],
  .pc-form-view .pc-action-bar > .pc-btn-primary {
    grid-area: submit !important;
    width: 100% !important;
    min-width: 0 !important;
    min-height: 48px !important;
    font-size: 14px !important;
    font-weight: 850 !important;
    border-radius: 14px !important;
    box-sizing: border-box !important;
  }
  .pc-form-view .pc-action-bar > a span { display: none !important; }

  body.sm-kb-open .pc-form-view .pc-action-bar {
    transform: translateY(115%) !important;
  }
}
</style>

<script id="sm-form-mobile-ux-js">
(function(){
  'use strict';
  var view = document.getElementById('pcForm');
  if (!view || view.dataset.smMobileUx === '1') return;
  view.dataset.smMobileUx = '1';
  var mq = window.matchMedia('(max-width: 768px)');
  var isMobile = function(){ return mq.matches; };
  var pills = Array.prototype.slice.call(view.querySelectorAll('.pc-step-pill'));
  var sections = Array.prototype.slice.call(view.querySelectorAll('.pc-section-card[id]'));
  var bar = view.querySelector('.pc-action-bar');
  var stepsLabel = <?= json_encode($smMobileStepsLabel, JSON_UNESCAPED_UNICODE) ?>;

  if (bar) bar.classList.remove('pc-submit');

  function scrollToSection(el){
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  document.addEventListener('click', function(ev){
    var pill = ev.target.closest && ev.target.closest('.pc-step-pill');
    if (!pill || !view.contains(pill) || !isMobile()) return;
    var target = document.querySelector(pill.dataset.scrollTarget || '');
    if (!target) return;
    ev.stopPropagation();
    ev.preventDefault();
    scrollToSection(target);
  }, true);

  if ('IntersectionObserver' in window && sections.length && pills.length) {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if (!en.isIntersecting) return;
        var id = '#' + en.target.id;
        pills.forEach(function(p){
          var on = p.dataset.scrollTarget === id;
          p.classList.toggle('is-current', on);
          if (on && isMobile() && p.parentElement) {
            var rail = p.parentElement;
            var left = p.offsetLeft - 14;
            if (left < rail.scrollLeft || left + p.offsetWidth > rail.scrollLeft + rail.clientWidth) {
              rail.scrollTo({ left: left, behavior: 'smooth' });
            }
          }
        });
      });
    }, { rootMargin: '-30% 0px -55% 0px', threshold: 0 });
    sections.forEach(function(s){ io.observe(s); });
  }

  // Progress Bar
  var fill = null, label = null;
  if (bar && pills.length) {
    var wrap = document.createElement('div');
    wrap.className = 'sm-m-progress-wrap';
    wrap.innerHTML = '<span class="sm-m-progress"><span class="sm-m-progress-fill"></span></span><span class="sm-m-progress-label"></span>';
    bar.insertBefore(wrap, bar.firstChild);
    fill = wrap.querySelector('.sm-m-progress-fill');
    label = wrap.querySelector('.sm-m-progress-label');
  }
  var raf = 0;
  function refreshProgress(){
    raf = 0;
    if (!fill) return;
    var done = pills.filter(function(p){ return p.classList.contains('is-done'); }).length;
    fill.style.width = Math.round(done / pills.length * 100) + '%';
    label.textContent = done + '/' + pills.length + ' ' + stepsLabel;
  }
  function queue(){ if (!raf) raf = requestAnimationFrame(refreshProgress); }
  if ('MutationObserver' in window && pills.length) {
    var mo = new MutationObserver(queue);
    pills.forEach(function(p){ mo.observe(p, { attributes: true, attributeFilter: ['class'] }); });
  }
  queue();

  // Keyboard awareness
  view.addEventListener('focusin', function(e){
    if (!isMobile()) return;
    var t = e.target;
    if (t && (t.tagName === 'TEXTAREA' || (t.tagName === 'INPUT' && !/checkbox|radio|button|submit/.test(t.type)))) {
      document.body.classList.add('sm-kb-open');
    }
  });
  view.addEventListener('focusout', function(){
    setTimeout(function(){
      var a = document.activeElement;
      if (!a || !(a.tagName === 'TEXTAREA' || a.tagName === 'INPUT')) document.body.classList.remove('sm-kb-open');
    }, 120);
  });

  // Auto-advance
  function nextSectionAfter(id){
    var idx = sections.findIndex(function(s){ return s.id === id; });
    return idx >= 0 ? sections[idx + 1] : null;
  }
  view.addEventListener('click', function(e){
    if (!isMobile()) return;
    var sub = e.target.closest && e.target.closest('.pc-subcat');
    if (sub) { setTimeout(function(){ scrollToSection(nextSectionAfter('pcSection1')); }, 260); }
  });
  window.addEventListener('schoolmanager:location-selected', function(){
    if (!isMobile()) return;
    setTimeout(function(){ scrollToSection(nextSectionAfter('pcSection2')); }, 320);
  });

  function revealActive(sel){
    var el = view.querySelector(sel);
    if (el && el.parentElement && isMobile()) {
      el.parentElement.scrollLeft = Math.max(0, el.offsetLeft - 16);
    }
  }
  revealActive('.pc-type-card.active');
  revealActive('.pc-parent-btn.active');
})();
</script>

<script id="sm-form-mobile-ux-js">
(function(){
  'use strict';
  var view = document.getElementById('pcForm');
  if (!view || view.dataset.smMobileUx === '1') return;
  view.dataset.smMobileUx = '1';
  var mq = window.matchMedia('(max-width: 768px)');
  var isMobile = function(){ return mq.matches; };
  var pills = Array.prototype.slice.call(view.querySelectorAll('.pc-step-pill'));
  var sections = Array.prototype.slice.call(view.querySelectorAll('.pc-section-card[id]'));
  var bar = view.querySelector('.pc-action-bar');
  var stepsLabel = <?= json_encode($smMobileStepsLabel, JSON_UNESCAPED_UNICODE) ?>;

  if (bar) bar.classList.remove('pc-submit');

  function scrollToSection(el){
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  document.addEventListener('click', function(ev){
    var pill = ev.target.closest && ev.target.closest('.pc-step-pill');
    if (!pill || !view.contains(pill) || !isMobile()) return;
    var target = document.querySelector(pill.dataset.scrollTarget || '');
    if (!target) return;
    ev.stopPropagation();
    ev.preventDefault();
    scrollToSection(target);
  }, true);

  if ('IntersectionObserver' in window && sections.length && pills.length) {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if (!en.isIntersecting) return;
        var id = '#' + en.target.id;
        pills.forEach(function(p){
          var on = p.dataset.scrollTarget === id;
          p.classList.toggle('is-current', on);
          if (on && isMobile() && p.parentElement) {
            var rail = p.parentElement;
            var left = p.offsetLeft - 14;
            if (left < rail.scrollLeft || left + p.offsetWidth > rail.scrollLeft + rail.clientWidth) {
              rail.scrollTo({ left: left, behavior: 'smooth' });
            }
          }
        });
      });
    }, { rootMargin: '-30% 0px -55% 0px', threshold: 0 });
    sections.forEach(function(s){ io.observe(s); });
  }

  // Progress Bar
  var fill = null, label = null;
  if (bar && pills.length) {
    var wrap = document.createElement('div');
    wrap.className = 'sm-m-progress-wrap';
    wrap.innerHTML = '<span class="sm-m-progress"><span class="sm-m-progress-fill"></span></span><span class="sm-m-progress-label"></span>';
    bar.insertBefore(wrap, bar.firstChild);
    fill = wrap.querySelector('.sm-m-progress-fill');
    label = wrap.querySelector('.sm-m-progress-label');
  }
  var raf = 0;
  function refreshProgress(){
    raf = 0;
    if (!fill) return;
    var done = pills.filter(function(p){ return p.classList.contains('is-done'); }).length;
    fill.style.width = Math.round(done / pills.length * 100) + '%';
    label.textContent = done + '/' + pills.length + ' ' + stepsLabel;
  }
  function queue(){ if (!raf) raf = requestAnimationFrame(refreshProgress); }
  if ('MutationObserver' in window && pills.length) {
    var mo = new MutationObserver(queue);
    pills.forEach(function(p){ mo.observe(p, { attributes: true, attributeFilter: ['class'] }); });
  }
  queue();

  // Keyboard awareness
  view.addEventListener('focusin', function(e){
    if (!isMobile()) return;
    var t = e.target;
    if (t && (t.tagName === 'TEXTAREA' || (t.tagName === 'INPUT' && !/checkbox|radio|button|submit/.test(t.type)))) {
      document.body.classList.add('sm-kb-open');
    }
  });
  view.addEventListener('focusout', function(){
    setTimeout(function(){
      var a = document.activeElement;
      if (!a || !(a.tagName === 'TEXTAREA' || a.tagName === 'INPUT')) document.body.classList.remove('sm-kb-open');
    }, 120);
  });

  // Auto-advance
  function nextSectionAfter(id){
    var idx = sections.findIndex(function(s){ return s.id === id; });
    return idx >= 0 ? sections[idx + 1] : null;
  }
  view.addEventListener('click', function(e){
    if (!isMobile()) return;
    var sub = e.target.closest && e.target.closest('.pc-subcat');
    if (sub) { setTimeout(function(){ scrollToSection(nextSectionAfter('pcSection1')); }, 260); }
  });
  window.addEventListener('schoolmanager:location-selected', function(){
    if (!isMobile()) return;
    setTimeout(function(){ scrollToSection(nextSectionAfter('pcSection2')); }, 320);
  });

  function revealActive(sel){
    var el = view.querySelector(sel);
    if (el && el.parentElement && isMobile()) {
      el.parentElement.scrollLeft = Math.max(0, el.offsetLeft - 16);
    }
  }
  revealActive('.pc-type-card.active');
  revealActive('.pc-parent-btn.active');
})();
</script>
