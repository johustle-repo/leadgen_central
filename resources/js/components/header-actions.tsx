import { createContext, useContext, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';

type HeaderActionsContextValue = {
    target: HTMLDivElement | null;
    setTarget: (element: HTMLDivElement | null) => void;
};

const HeaderActionsContext = createContext<HeaderActionsContextValue | null>(
    null,
);

/**
 * Lets a page render interactive controls (buttons tied to its own local
 * state) into the persistent top header, which lives in a sibling part of
 * the layout tree and can't receive JSX through the static `Page.layout`
 * breadcrumbs convention. Wrap the layout once with the provider, mark the
 * spot in the header with `HeaderActionsSlot`, and have a page portal its
 * controls into it with `HeaderActionsPortal`.
 */
export function HeaderActionsProvider({ children }: { children: ReactNode }) {
    const [target, setTarget] = useState<HTMLDivElement | null>(null);

    return (
        <HeaderActionsContext.Provider value={{ target, setTarget }}>
            {children}
        </HeaderActionsContext.Provider>
    );
}

export function HeaderActionsSlot({ className }: { className?: string }) {
    const context = useContext(HeaderActionsContext);

    return <div ref={context?.setTarget} className={className ?? 'contents'} />;
}

export function HeaderActionsPortal({ children }: { children: ReactNode }) {
    const context = useContext(HeaderActionsContext);

    if (!context?.target) {
        return null;
    }

    return createPortal(children, context.target);
}
