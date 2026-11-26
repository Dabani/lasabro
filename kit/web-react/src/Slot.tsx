import { Component, type ErrorInfo, type ReactNode } from 'react';
import { canAny, type SlotName } from '@lasabro/kit';
import { useKit } from './KitProvider';

/** A broken plugin component must not take the shell down: it renders nothing and the error is logged. */
class ContributionBoundary extends Component<{ id: string; children: ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error(`LASABRO: slot contribution "${this.props.id}" failed`, error, info.componentStack);
  }
  render() { return this.state.failed ? null : this.props.children; }
}

/**
 * Renders what active plugins contributed to a named extension point, ordered by their `order`.
 * `can` is the signed-in user's permission test; contributions that need a permission are hidden without it.
 * Props given to the slot are passed to every contribution (for example the current record).
 */
export function Slot({ name, can, props }: { name: SlotName; can?: (code: string) => boolean; props?: Record<string, unknown> }) {
  const contributions = useKit().resolved.slots.get(name);
  if (!contributions?.length) return null;
  const allowed = (code: string) => (can ? can(code) : false);
  return (
    <>
      {contributions
        .filter((c) => canAny(allowed, c.permission))
        .map(({ id, component: Contribution }) => (
          <ContributionBoundary key={id} id={id}><Contribution {...props} /></ContributionBoundary>
        ))}
    </>
  );
}
