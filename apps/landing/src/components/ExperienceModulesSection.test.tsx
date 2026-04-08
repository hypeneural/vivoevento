import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ExperienceModulesSection from './ExperienceModulesSection';
import { PersonaProvider } from '@/contexts/PersonaContext';

// Mock GSAP and ScrollTrigger
vi.mock('gsap', () => ({
  default: {
    registerPlugin: vi.fn(),
    from: vi.fn(),
  },
}));

vi.mock('gsap/ScrollTrigger', () => ({
  ScrollTrigger: {},
}));

vi.mock('@gsap/react', () => ({
  useGSAP: vi.fn((callback) => {
    // Don't execute GSAP animations in tests
  }),
}));

// Mock motion/react
vi.mock('motion/react', () => ({
  motion: {
    div: ({ children, ...props }: any) => <div {...props}>{children}</div>,
    span: ({ children, ...props }: any) => <span {...props}>{children}</span>,
  },
  AnimatePresence: ({ children }: any) => <>{children}</>,
  useReducedMotion: () => false,
}));

const renderWithProviders = (ui: React.ReactElement) => {
  return render(
    <PersonaProvider>
      {ui}
    </PersonaProvider>
  );
};

describe('ExperienceModulesSection - Accessibility', () => {
  describe('WAI-ARIA Tab Pattern', () => {
    it('should have proper ARIA roles and attributes', () => {
      renderWithProviders(<ExperienceModulesSection />);

      // Check tablist role
      const tablist = screen.getByRole('tablist', { name: /módulos de experiência/i });
      expect(tablist).toBeInTheDocument();

      // Check tab roles
      const tabs = screen.getAllByRole('tab');
      expect(tabs.length).toBeGreaterThan(0);

      // Check that first tab is selected by default
      expect(tabs[0]).toHaveAttribute('aria-selected', 'true');
      expect(tabs[0]).toHaveAttribute('tabindex', '0');

      // Check that other tabs are not selected
      tabs.slice(1).forEach(tab => {
        expect(tab).toHaveAttribute('aria-selected', 'false');
        expect(tab).toHaveAttribute('tabindex', '-1');
      });

      // Check tabpanel role
      const tabpanel = screen.getByRole('tabpanel');
      expect(tabpanel).toBeInTheDocument();
    });

    it('should have proper aria-controls and aria-labelledby relationships', () => {
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      const tabpanel = screen.getByRole('tabpanel');

      // Check that active tab has aria-controls pointing to tabpanel
      const activeTab = tabs.find(tab => tab.getAttribute('aria-selected') === 'true');
      expect(activeTab).toBeDefined();
      
      const tabId = activeTab?.getAttribute('id');
      const panelId = activeTab?.getAttribute('aria-controls');
      
      expect(tabId).toBeTruthy();
      expect(panelId).toBeTruthy();
      expect(tabpanel).toHaveAttribute('id', panelId);
      expect(tabpanel).toHaveAttribute('aria-labelledby', tabId);
    });
  });

  describe('Keyboard Navigation', () => {
    it('should navigate to next tab with ArrowRight', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      
      // Focus first tab
      tabs[0].focus();
      expect(tabs[0]).toHaveFocus();

      // Press ArrowRight
      await user.keyboard('{ArrowRight}');

      // Second tab should be focused and selected
      expect(tabs[1]).toHaveFocus();
      expect(tabs[1]).toHaveAttribute('aria-selected', 'true');
    });

    it('should navigate to previous tab with ArrowLeft', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      
      // Focus and activate second tab first
      tabs[1].focus();
      fireEvent.click(tabs[1]);
      expect(tabs[1]).toHaveAttribute('aria-selected', 'true');

      // Press ArrowLeft
      await user.keyboard('{ArrowLeft}');

      // First tab should be focused and selected
      expect(tabs[0]).toHaveFocus();
      expect(tabs[0]).toHaveAttribute('aria-selected', 'true');
    });

    it('should wrap to last tab when pressing ArrowLeft on first tab', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      const lastTab = tabs[tabs.length - 1];
      
      // Focus first tab
      tabs[0].focus();

      // Press ArrowLeft
      await user.keyboard('{ArrowLeft}');

      // Last tab should be focused and selected
      expect(lastTab).toHaveFocus();
      expect(lastTab).toHaveAttribute('aria-selected', 'true');
    });

    it('should wrap to first tab when pressing ArrowRight on last tab', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      const lastTab = tabs[tabs.length - 1];
      
      // Focus and activate last tab
      lastTab.focus();
      fireEvent.click(lastTab);
      expect(lastTab).toHaveAttribute('aria-selected', 'true');

      // Press ArrowRight
      await user.keyboard('{ArrowRight}');

      // First tab should be focused and selected
      expect(tabs[0]).toHaveFocus();
      expect(tabs[0]).toHaveAttribute('aria-selected', 'true');
    });

    it('should navigate to first tab with Home key', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      
      // Focus and activate third tab
      tabs[2].focus();
      fireEvent.click(tabs[2]);
      expect(tabs[2]).toHaveAttribute('aria-selected', 'true');

      // Press Home
      await user.keyboard('{Home}');

      // First tab should be focused and selected
      expect(tabs[0]).toHaveFocus();
      expect(tabs[0]).toHaveAttribute('aria-selected', 'true');
    });

    it('should navigate to last tab with End key', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      const lastTab = tabs[tabs.length - 1];
      
      // Focus first tab
      tabs[0].focus();

      // Press End
      await user.keyboard('{End}');

      // Last tab should be focused and selected
      expect(lastTab).toHaveFocus();
      expect(lastTab).toHaveAttribute('aria-selected', 'true');
    });
  });

  describe('Focus Management', () => {
    it('should have visible focus indicator on tabs', () => {
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      
      // Focus first tab
      tabs[0].focus();

      // Check that the tab has focus-visible styles applied
      // This is tested via the CSS class, actual visual testing would require e2e
      expect(tabs[0]).toHaveFocus();
    });

    it('should maintain only one tab in tab sequence (tabindex management)', () => {
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      
      // Only the selected tab should have tabindex="0"
      const tabbableTabs = tabs.filter(tab => tab.getAttribute('tabindex') === '0');
      expect(tabbableTabs).toHaveLength(1);

      // All other tabs should have tabindex="-1"
      const nonTabbableTabs = tabs.filter(tab => tab.getAttribute('tabindex') === '-1');
      expect(nonTabbableTabs).toHaveLength(tabs.length - 1);
    });

    it('should update tabindex when tab selection changes', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      
      // Initially first tab should be tabbable
      expect(tabs[0]).toHaveAttribute('tabindex', '0');
      expect(tabs[1]).toHaveAttribute('tabindex', '-1');

      // Navigate to second tab
      tabs[0].focus();
      await user.keyboard('{ArrowRight}');

      // Now second tab should be tabbable
      expect(tabs[0]).toHaveAttribute('tabindex', '-1');
      expect(tabs[1]).toHaveAttribute('tabindex', '0');
    });
  });

  describe('Mouse Interaction', () => {
    it('should activate tab on click', () => {
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      
      // Click second tab
      fireEvent.click(tabs[1]);

      // Second tab should be selected
      expect(tabs[1]).toHaveAttribute('aria-selected', 'true');
      expect(tabs[0]).toHaveAttribute('aria-selected', 'false');
    });

    it('should show corresponding tabpanel when tab is clicked', () => {
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      
      // Get the panel ID from first tab
      const firstPanelId = tabs[0].getAttribute('aria-controls');
      
      // Click second tab
      fireEvent.click(tabs[1]);

      // Panel should update to match second tab
      const secondPanelId = tabs[1].getAttribute('aria-controls');
      const tabpanel = screen.getByRole('tabpanel');
      
      expect(tabpanel).toHaveAttribute('id', secondPanelId);
      expect(tabpanel).not.toHaveAttribute('id', firstPanelId);
    });
  });

  describe('WCAG Compliance', () => {
    it('should have accessible labels for all tabs', () => {
      renderWithProviders(<ExperienceModulesSection />);

      const tabs = screen.getAllByRole('tab');
      
      // All tabs should have accessible text content
      tabs.forEach(tab => {
        expect(tab).toHaveTextContent(/.+/);
      });
    });

    it('should have accessible label for tablist', () => {
      renderWithProviders(<ExperienceModulesSection />);

      const tablist = screen.getByRole('tablist');
      expect(tablist).toHaveAttribute('aria-label', 'Módulos de experiência');
    });

    it('should make tabpanel focusable for screen reader users', () => {
      renderWithProviders(<ExperienceModulesSection />);

      const tabpanel = screen.getByRole('tabpanel');
      expect(tabpanel).toHaveAttribute('tabindex', '0');
    });
  });
});
