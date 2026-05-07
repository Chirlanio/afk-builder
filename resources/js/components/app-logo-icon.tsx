import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" {...props}>
            <defs>
                <linearGradient id="afk-shield-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#fde047" />
                    <stop offset="50%" stopColor="#f59e0b" />
                    <stop offset="100%" stopColor="#b45309" />
                </linearGradient>
                <linearGradient id="afk-letter-grad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stopColor="#fef3c7" />
                    <stop offset="100%" stopColor="#fbbf24" />
                </linearGradient>
            </defs>

            <path
                d="M20 1.5 L36.5 11 L36.5 29 L20 38.5 L3.5 29 L3.5 11 Z"
                fill="#0a0613"
                stroke="url(#afk-shield-grad)"
                strokeWidth="2"
                strokeLinejoin="round"
            />

            <path
                d="M20 5 L33 12.5 L33 27.5 L20 35 L7 27.5 L7 12.5 Z"
                fill="none"
                stroke="#fbbf24"
                strokeWidth="0.5"
                strokeOpacity="0.35"
            />

            <path
                d="M13.5 28.5 L20 11 L26.5 28.5 M16.2 22.5 L23.8 22.5"
                stroke="url(#afk-letter-grad)"
                strokeWidth="2.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                fill="none"
            />

            <circle cx="20" cy="11" r="1.3" fill="#fde047" />
        </svg>
    );
}
