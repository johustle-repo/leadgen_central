import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M8 10.5 20 4l12 6.5v13L20 30 8 23.5v-13Z"
                stroke="currentColor"
                strokeWidth="2.7"
                strokeLinejoin="round"
            />
            <path
                d="m8 10.5 12 6.7 12-6.7M20 17.2V30"
                stroke="currentColor"
                strokeWidth="2.7"
                strokeLinejoin="round"
            />
            <path
                d="M13.5 27.2v5.1L20 36l6.5-3.7v-5.1"
                stroke="currentColor"
                strokeWidth="2.7"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="20" cy="17.2" r="2.6" fill="currentColor" />
        </svg>
    );
}
