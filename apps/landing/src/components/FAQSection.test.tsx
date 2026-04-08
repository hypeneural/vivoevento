import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import FAQSection from './FAQSection';
import { faqs } from '@/data/landing';

describe('FAQSection - Accessibility and Functionality', () => {
  describe('WAI-ARIA Accordion Pattern', () => {
    it('should have proper ARIA roles and attributes', () => {
      render(<FAQSection />);

      // Check section has proper labeling
      const section = screen.getByRole('region', { name: /perguntas frequentes/i });
      expect(section).toBeInTheDocument();

      // Check all accordion buttons have proper ARIA attributes
      const buttons = screen.getAllByRole('button');
      expect(buttons).toHaveLength(faqs.length);

      buttons.forEach((button) => {
        expect(button).toHaveAttribute('aria-expanded');
        expect(button).toHaveAttribute('aria-controls');
      });
    });

    it('should have proper heading structure', () => {
      render(<FAQSection />);

      // Main section title should be h2
      const mainTitle = screen.getByRole('heading', { level: 2, name: /perguntas que normalmente travam/i });
      expect(mainTitle).toBeInTheDocument();

      // Each FAQ item should have h3 heading
      const faqHeadings = screen.getAllByRole('heading', { level: 3 });
      expect(faqHeadings).toHaveLength(faqs.length);
    });

    it('should start with all panels closed', () => {
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');
      buttons.forEach((button) => {
        expect(button).toHaveAttribute('aria-expanded', 'false');
      });
    });

    it('should open panel when button is clicked', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const firstButton = screen.getAllByRole('button')[0];
      await user.click(firstButton);

      expect(firstButton).toHaveAttribute('aria-expanded', 'true');
    });

    it('should close previously open panel when opening a new one (only one open at a time)', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');
      
      // Open first panel
      await user.click(buttons[0]);
      expect(buttons[0]).toHaveAttribute('aria-expanded', 'true');

      // Open second panel
      await user.click(buttons[1]);
      expect(buttons[1]).toHaveAttribute('aria-expanded', 'true');
      expect(buttons[0]).toHaveAttribute('aria-expanded', 'false');
    });

    it('should toggle panel when clicking the same button twice', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const firstButton = screen.getAllByRole('button')[0];
      
      // Open
      await user.click(firstButton);
      expect(firstButton).toHaveAttribute('aria-expanded', 'true');

      // Close
      await user.click(firstButton);
      expect(firstButton).toHaveAttribute('aria-expanded', 'false');
    });
  });

  describe('Keyboard Navigation', () => {
    it('should navigate to next button with ArrowDown', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');
      
      // Focus first button
      buttons[0].focus();
      expect(buttons[0]).toHaveFocus();

      // Press ArrowDown
      await user.keyboard('{ArrowDown}');
      expect(buttons[1]).toHaveFocus();
    });

    it('should navigate to previous button with ArrowUp', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');
      
      // Focus second button
      buttons[1].focus();
      expect(buttons[1]).toHaveFocus();

      // Press ArrowUp
      await user.keyboard('{ArrowUp}');
      expect(buttons[0]).toHaveFocus();
    });

    it('should wrap to first button when pressing ArrowDown on last button', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');
      const lastButton = buttons[buttons.length - 1];
      
      // Focus last button
      lastButton.focus();
      expect(lastButton).toHaveFocus();

      // Press ArrowDown
      await user.keyboard('{ArrowDown}');
      expect(buttons[0]).toHaveFocus();
    });

    it('should wrap to last button when pressing ArrowUp on first button', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');
      
      // Focus first button
      buttons[0].focus();
      expect(buttons[0]).toHaveFocus();

      // Press ArrowUp
      await user.keyboard('{ArrowUp}');
      expect(buttons[buttons.length - 1]).toHaveFocus();
    });

    it('should navigate to first button with Home key', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');
      
      // Focus middle button
      buttons[3].focus();
      expect(buttons[3]).toHaveFocus();

      // Press Home
      await user.keyboard('{Home}');
      expect(buttons[0]).toHaveFocus();
    });

    it('should navigate to last button with End key', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');
      
      // Focus first button
      buttons[0].focus();
      expect(buttons[0]).toHaveFocus();

      // Press End
      await user.keyboard('{End}');
      expect(buttons[buttons.length - 1]).toHaveFocus();
    });
  });

  describe('Content Requirements', () => {
    it('should display 7-10 FAQ items', () => {
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThanOrEqual(7);
      expect(buttons.length).toBeLessThanOrEqual(10);
    });

    it('should include required commercial questions', () => {
      render(<FAQSection />);

      // Check for required questions (using more specific queries)
      expect(screen.getByText(/precisa de app/i)).toBeInTheDocument();
      expect(screen.getByText(/aceita vídeo/i)).toBeInTheDocument();
      expect(screen.getByText(/muito volume/i)).toBeInTheDocument();
      expect(screen.getByText(/como funciona a moderação/i)).toBeInTheDocument();
      expect(screen.getByText(/busca facial é configurável/i)).toBeInTheDocument();
      // Use getAllByText for text that appears in both question and answer
      expect(screen.getAllByText(/casamento.*formatura/i)[0]).toBeInTheDocument();
      expect(screen.getByText(/branding/i)).toBeInTheDocument();
    });

    it('should have concise answers (≤3 lines)', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const buttons = screen.getAllByRole('button');

      // Open each panel and check answer length
      for (let i = 0; i < buttons.length; i++) {
        await user.click(buttons[i]);
        
        const panelId = buttons[i].getAttribute('aria-controls');
        const panel = document.getElementById(panelId!);
        const answer = panel?.querySelector('p')?.textContent || '';
        
        // Count approximate lines (assuming ~80 chars per line)
        const approximateLines = Math.ceil(answer.length / 80);
        expect(approximateLines).toBeLessThanOrEqual(3);
      }
    });
  });

  describe('Visual Indicators', () => {
    it('should have chevron icon that rotates when panel is open', async () => {
      const user = userEvent.setup();
      render(<FAQSection />);

      const firstButton = screen.getAllByRole('button')[0];
      const chevron = firstButton.querySelector('svg');
      
      expect(chevron).toBeInTheDocument();
      expect(chevron).toHaveAttribute('aria-hidden', 'true');

      // Open panel
      await user.click(firstButton);
      
      // Check if parent item has 'open' class (CSS modules generate hashed class names)
      const item = firstButton.closest('article');
      expect(item?.className).toMatch(/open/i);
    });
  });

  describe('Semantic HTML', () => {
    it('should use semantic HTML5 elements', () => {
      render(<FAQSection />);

      // Section element
      const section = document.querySelector('section#faq');
      expect(section).toBeInTheDocument();

      // Article elements for each FAQ item
      const articles = document.querySelectorAll('article');
      expect(articles).toHaveLength(faqs.length);
    });
  });
});
