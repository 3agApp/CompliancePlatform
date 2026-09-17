import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <g
                fill="none"
                stroke="currentColor"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
            >
                <path d="M12 3 5 6v5c0 4.5 2.9 7.9 7 9 4.1-1.1 7-4.5 7-9V6l-7-3z" />
                <path d="m9 12 2 2 4-4" />
            </g>
        </svg>
    );
}
