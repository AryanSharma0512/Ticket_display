// JavaScript for creating and animating stars

document.addEventListener('DOMContentLoaded', function () {
    const numStars = 100; // Adjust number of stars as needed

    for (let i = 0; i < numStars; i++) {
        createStar();
    }
});

function createStar() {
    const star = document.createElement('div');
    star.classList.add('star');
    star.style.left = `${Math.random() * 100}vw`; // Random horizontal position
    star.style.top = `${Math.random() * 100}vh`; // Random vertical position
    star.style.animationDuration = `${Math.random() * 2 + 1}s`; // Random animation duration
    document.body.appendChild(star);
}